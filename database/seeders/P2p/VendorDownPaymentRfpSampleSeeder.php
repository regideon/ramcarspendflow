<?php

namespace Database\Seeders\P2p;

use App\Enums\RfpRequestType;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Actions\ApProcessingActions;
use App\Models\CompanyRole;
use App\Models\Currency;
use App\Models\P2p\Invoice;
use App\Models\PaymentMethod;
use App\Models\Rfp\ApprovalStep;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\Subcategory;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\WithholdingTaxCode;
use App\Services\Rfp\ApprovalEngine;
use App\Services\Rfp\RfpTaxProcessing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fully approved supplier down payment RFPs (Vendor DP) for the vendor and PO of the
 * P2P approvals sample (P2pApprovalSampleSeeder, PO 4500099001), so Bulk Accounts
 * Payable can show and link the vendor's down payments received from RFP.
 *
 * Each RFP goes through the real workflow (ApprovalEngine): submitted by the demo
 * requestor, then approved step by step by the demo user holding each role in the
 * invoice's company, ending Completed (payment released). Re-runnable: it removes
 * and recreates only its own RFPs (invoice numbers DP-SAMPLE-*).
 */
class VendorDownPaymentRfpSampleSeeder extends Seeder
{
    public const string INVOICE_PREFIX = 'DP-SAMPLE-';

    /**
     * Down payments on the sample PO: [percent of the PO, days ago it was raised].
     *
     * @var list<array{0: int, 1: int}>
     */
    public const array DOWN_PAYMENTS = [
        [30, 30],
        [10, 12],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('VendorDownPaymentRfpSampleSeeder creates demo data and must not run in production.');
        }

        $invoice = Invoice::query()->with(['vendor', 'company', 'lines'])
            ->whereJsonContains('po_numbers', P2pApprovalSampleSeeder::PO_NUMBER)
            ->latest('id')
            ->first() ?? throw new RuntimeException('Run P2pApprovalSampleSeeder first: no P2P invoice for PO '.P2pApprovalSampleSeeder::PO_NUMBER.'.');

        $subcategory = Subcategory::query()->where('is_active', true)->where('requires_po_number', true)->where('name', 'Advances to Supplier')->first()
            ?? Subcategory::query()->where('requires_po_number', true)->orderByDesc('is_active')->firstOrFail();
        $requestor = User::query()->where('email', 'requestor@ramcar.com')->firstOrFail();
        $poTotal = $invoice->lines->sum(fn ($line): float => (float) $line->quantity_billed * (float) $line->unit_cost);

        // DatabaseSeeder turns model events off; these RFPs need them (numbering, G/L, due date, routing)
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            $this->seedDownPayments($invoice, $subcategory, $requestor, $poTotal);
        } finally {
            $dispatcher ? Model::setEventDispatcher($dispatcher) : Model::unsetEventDispatcher();
        }
    }

    protected function seedDownPayments(Invoice $invoice, Subcategory $subcategory, User $requestor, float $poTotal): void
    {
        $this->purge();
        $this->grantDemoApproversIn($invoice->company_id);

        foreach (self::DOWN_PAYMENTS as $index => [$percent, $daysAgo]) {
            $amount = number_format($poTotal * $percent / 100, 2, '.', '');
            $number = self::INVOICE_PREFIX.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            // Raise and approve it on past dates, a day per step, so the history reads like a real one
            Carbon::setTestNow(CarbonImmutable::now()->subDays($daysAgo)->setTime(9, 0));

            try {
                $rfp = $this->raise($invoice, $requestor, $subcategory, $number, $percent, $amount);
                $rfp->submit($requestor);
                $this->approveThrough($rfp->refresh());
            } finally {
                Carbon::setTestNow();
            }
        }
    }

    protected function raise(Invoice $invoice, User $requestor, Subcategory $subcategory, string $number, int $percent, string $amount): Rfp
    {
        $rfp = Rfp::query()->create([
            'company_id' => $invoice->company_id,
            'requestor_id' => $requestor->id,
            'request_type' => RfpRequestType::Standard,
            'rfp_due_type_id' => DueType::query()->where('code', 'standard')->value('id'),
            'account_group_id' => $invoice->vendor?->account_group_id,
            'vendor_id' => $invoice->vendor_id,
            'payment_method_id' => PaymentMethod::query()->where('code', 'T')->value('id'),
            'purpose' => "Supplier down payment ({$percent}%) for PO ".P2pApprovalSampleSeeder::PO_NUMBER.' — to be applied against the vendor invoice in P2P.',
            'is_direct_to_vp' => false,
            'is_pre_approved' => false,
        ]);

        $path = "rfp-attachments/{$number}.pdf";
        Storage::disk('local')->put($path, $this->pdf([
            'SUPPLIER DOWN PAYMENT REQUEST (SAMPLE)',
            '',
            'Reference : '.$number,
            'Vendor    : '.$invoice->vendor?->name.' ('.$invoice->vendor?->code.')',
            'PO number : '.P2pApprovalSampleSeeder::PO_NUMBER,
            "Down payment: {$percent}% of PO = PHP ".number_format((float) $amount, 2),
        ]));

        $rfp->items()->create([
            'rfp_subcategory_id' => $subcategory->id,
            'po_number' => P2pApprovalSampleSeeder::PO_NUMBER,
            'invoice_number' => $number,
            'invoice_date' => today(),
            'description' => "Down payment {$percent}% - PO ".P2pApprovalSampleSeeder::PO_NUMBER,
            'currency_id' => Currency::query()->where('code', 'PHP')->value('id'),
            'amount' => $amount,
            'attachments' => [$path],
        ]);

        return $rfp;
    }

    /**
     * Approve every step with the demo user who holds its role in the RFP's company.
     * AP steps save tax codes as on screen: a down payment has no VAT or EWT yet.
     */
    protected function approveThrough(Rfp $rfp): void
    {
        $engine = app(ApprovalEngine::class);
        $taxes = app(RfpTaxProcessing::class);
        $zeroRated = TaxCode::query()->where('code', 'I0')->value('id');
        $noWithholding = WithholdingTaxCode::query()->where('code', '00')->value('id');

        for ($guard = 0; $rfp->status->isAwaitingApproval() && $guard < 20; $guard++) {
            Carbon::setTestNow(Carbon::now()->addDay());

            /** @var ApprovalStep $step */
            $step = $rfp->approvalSteps()->where('round', $rfp->approval_round)->where('state', 'active')->orderBy('sequence')->firstOrFail();
            $approver = $this->demoUserFor($step->role, $rfp->company_id);

            $lines = collect(ApProcessingActions::linesFor($rfp)['lines'])
                ->map(fn (array $line): array => [...$line, 'tax_code_id' => $line['tax_code_id'] ?? $zeroRated, 'withholding_tax_code_id' => $line['withholding_tax_code_id'] ?? $noWithholding])
                ->all();

            match (true) {
                $step->kind === RfpStageKind::ApProcessing => $taxes->process($rfp, $approver, $lines, verb: 'Tax codes processed'),
                $step->role === RoleName::ApSupervisor => $taxes->process($rfp, $approver, $lines, verb: 'Approved', lockInputTax: true),
                $step->role === RoleName::ApManager => $taxes->process($rfp, $approver, $lines, verb: 'Completed', lockInputTax: true),
                $step->kind === RfpStageKind::Payment => $engine->approve($rfp, $approver, 'Down payment released to the vendor.', verb: 'Payment released'),
                $step->status === RfpStatus::ForApReview => $engine->approve($rfp, $approver, verb: 'Endorsed'),
                default => $engine->approve($rfp, $approver),
            };

            $rfp->refresh();
        }

        if ($rfp->status !== RfpStatus::Completed) {
            throw new RuntimeException("{$rfp->rfp_number} stopped at {$rfp->status->getLabel()} instead of Completed.");
        }
    }

    protected function demoUserFor(?RoleName $role, int $companyId): User
    {
        return User::query()
            ->where('email', 'like', '%@ramcar.com')
            ->whereHas('companyRoles', fn ($query) => $query->where('company_id', $companyId)->where('role', $role?->value))
            ->orderBy('id')
            ->first() ?? throw new RuntimeException("No demo user holds {$role?->value} in company {$companyId}.");
    }

    /**
     * The demo approvers hold their roles in the main companies; give them the same
     * roles in the sample invoice's company so they can approve its down payments.
     */
    protected function grantDemoApproversIn(int $companyId): void
    {
        CompanyRole::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'like', '%@ramcar.com'))
            ->get()
            ->unique(fn (CompanyRole $assignment): string => $assignment->user_id.'|'.$assignment->role->value)
            ->each(fn (CompanyRole $assignment) => CompanyRole::query()->firstOrCreate([
                'user_id' => $assignment->user_id,
                'company_id' => $companyId,
                'role' => $assignment->role->value,
            ]));
    }

    protected function purge(): void
    {
        Rfp::withTrashed()
            ->whereHas('items', fn ($query) => $query->where('invoice_number', 'like', self::INVOICE_PREFIX.'%'))
            ->get()
            ->each(fn (Rfp $rfp) => $rfp->forceDelete());

        Item::query()->where('invoice_number', 'like', self::INVOICE_PREFIX.'%')->delete();
    }

    /**
     * A one-page text PDF for the supporting document.
     *
     * @param  list<string>  $lines
     */
    protected function pdf(array $lines): string
    {
        $text = collect($lines)->values()->map(fn (string $line, int $index): string => sprintf(
            'BT /F1 11 Tf 56 %d Td (%s) Tj ET',
            780 - ($index * 18),
            str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line),
        ))->implode("\n");

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
