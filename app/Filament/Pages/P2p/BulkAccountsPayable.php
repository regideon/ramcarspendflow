<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use App\Services\P2p\P2pEscalationRoutingService;
use App\Services\P2p\P2pGoodReceiptReturnService;
use App\Services\P2p\P2pTaxSummaryService;
use App\Services\P2p\P2pThreeWayMatchService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The AP processor's processing screen for one submission. Review the vendor's
 * lines and attachments side by side with the PO/GR mirror, assign the tax and
 * withholding tax, then submit to the checker, escalate selected lines, or return
 * the submission to the vendor.
 *
 * The checker branch is out of scope (blocked on B1: which role is "AP Checker").
 */
class BulkAccountsPayable extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'p2p/bulk-accounts-payable';

    protected static ?string $title = 'Process Accounts Payable';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    /**
     * The submission being processed.
     */
    #[Url]
    public ?int $invoice = null;

    public ?string $vendor_name = null;

    public ?string $company_name = null;

    public ?string $grc_number = null;

    /**
     * @var array<int, string>
     */
    public array $reference_number = [];

    /**
     * @var array<int, string>
     */
    public array $attachments = [];

    public ?string $invoiceAt = null;

    public ?string $invoice_number = null;

    /**
     * The vendor's submitted total, shown for reference beside the computed summary.
     */
    public string $invoice_total = '0.00';

    public bool $isViewOnly = false;

    /**
     * Tax section state.
     */
    public ?string $withholding_tax_code = null;

    public ?string $withholding_tax_base = null;

    public ?string $tax_code = null;

    public ?string $remarks_ap = null;

    /**
     * Computed tax summary, mirrored into disabled inputs.
     */
    public string $vatable_sales = '0.00';

    public string $vat_exempt = '0.00';

    public string $vat_zero_rated = '0.00';

    public string $vat = '0.00';

    public string $total = '0.00';

    public string $ewt = '0.00';

    public string $net = '0.00';

    /**
     * One entry per GR, each with its line rows (the Medius comparison table).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $goods_receipts = [];

    public ?string $escalationReason = null;

    public string $escalationRemarks = '';

    protected ?Invoice $loadedInvoice = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::ApProcessor, RoleName::Admin]) ?? false;
    }

    public function mount(): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            $this->redirect(ApprovalResource::getUrl('index'));

            return;
        }

        $this->authorizeAccess($invoice);

        $this->grc_number = $invoice->grc_number;
        $this->reference_number = array_values($invoice->reference_number ?? []);
        $this->attachments = array_values($invoice->attachments ?? []);
        $this->invoiceAt = optional($invoice->invoice_at)->toDateString();
        $this->invoice_number = $invoice->invoice_number;
        $this->invoice_total = number_format((float) $invoice->amount_submitted, 2, '.', '');

        $this->vendor_name = $invoice->vendor?->name ?? 'N/A';
        $this->company_name = $invoice->company?->name ?? 'N/A';

        $this->withholding_tax_code = $invoice->withholding_tax_code;
        $this->withholding_tax_base = $invoice->withholding_tax_base !== null
            ? (string) $invoice->withholding_tax_base
            : null;
        $this->tax_code = $invoice->tax_code;
        $this->remarks_ap = $invoice->remarks_ap;

        $this->goods_receipts = $this->buildGoodsReceipts($invoice);

        $this->syncSummaryFromInvoice($invoice);
    }

    /**
     * What the AP Processor should check from Read invoice with AI: flagged lines, the vendor's
     * remarks, warnings about the invoice, invoice lines not billed and extra charges.
     */
    protected function invoiceMatchSummary(): HtmlString
    {
        $match = $this->invoiceModel()?->invoice_match ?? [];
        $list = fn (iterable $items): string => '<ul style="margin:.25rem 0 .5rem;padding-left:1.125rem;list-style:disc;">'
            .collect($items)->map(fn (string $item): string => '<li>'.e($item).'</li>')->implode('').'</ul>';

        $sections = array_filter([
            filled($match['flags'] ?? []) ? '<strong>Lines</strong>'.$list(collect($match['flags'])->map(fn (array $flag): string => sprintf(
                'GR %s · %s (PO %s/%s): %s',
                $flag['gr_number'] ?? '—',
                $flag['item_name'] ?? '—',
                $flag['purchase_order'] ?? '—',
                $flag['purchase_order_item'] ?? '—',
                implode(' ', $flag['reasons'] ?? []),
            ))) : null,
            filled($match['vendor_remarks'] ?? null) ? '<strong>Vendor\'s remarks</strong>'.$list([$match['vendor_remarks']]) : null,
            filled($match['warnings'] ?? []) ? '<strong>Invoice</strong>'.$list($match['warnings']) : null,
            filled($match['unmatched_lines'] ?? []) ? '<strong>On the invoice but not billed here</strong>'.$list(collect($match['unmatched_lines'])->map(fn (array $line): string => sprintf(
                '%s: %s · qty %s @ %s',
                $line['invoice_number'] ?? '—',
                $line['description'] ?? '—',
                $line['quantity'] ?? '—',
                is_numeric($line['unit_price'] ?? null) ? number_format((float) $line['unit_price'], 2) : '—',
            ))) : null,
            filled($match['charges'] ?? []) ? '<strong>Charges not on any PO</strong>'.$list(collect($match['charges'])->map(fn (array $charge): string => sprintf(
                '%s: %s · %s',
                $charge['invoice_number'] ?? '—',
                $charge['description'] ?? 'Charge',
                number_format((float) $charge['amount'], 2),
            ))) : null,
        ]);

        return new HtmlString(implode('', $sections));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // The vendor submitted lines that don't match their invoice (Read invoice with AI)
            Callout::make(fn (): string => ($count = $this->invoiceModel()?->match_flag_count ?? 0) > 0
                ? "{$count} ".str('line')->plural($count).' to check against the vendor\'s invoice'
                : 'Check the vendor\'s invoice')
                ->description(fn (): HtmlString => $this->invoiceMatchSummary())
                ->warning()
                ->visible(fn (): bool => $this->invoiceMatchSummary()->toHtml() !== '')
                ->columnSpanFull(),

            Section::make('Submission')
                ->description('Submitted by the vendor; compare against the PO and goods receipts before submitting.')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->schema([
                    Grid::make()
                        ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->schema([
                            TextInput::make('vendor_name')
                                ->label('Vendor')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('company_name')
                                ->label('Company')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('grc_number')
                                ->label('GRC No.')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('invoiceAt')
                                ->label('Invoice Date')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('reference_number_display')
                                ->label('Reference No.')
                                ->readOnly()
                                ->dehydrated(false)
                                ->formatStateUsing(fn (): string => implode(', ', $this->reference_number)),

                            ViewField::make('attachments')
                                ->label('Attached PDF')
                                ->view('filament.components.open-pdf-link')
                                ->viewData(fn (): array => ['attachments' => $this->attachments])
                                ->dehydrated(false),

                            TextInput::make('invoice_number')
                                ->label('Invoice No.')
                                ->readOnly()
                                ->dehydrated(false)
                                ->placeholder('SAP-populated on posting'),
                        ]),
                ]),

            Section::make('Tax')
                ->description('Applies to the whole submission.')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->schema([
                    Grid::make()
                        ->columns(['default' => 1, 'md' => 12])
                        ->schema([
                            Select::make('withholding_tax_code')
                                ->label('Withholding Tax Code')
                                ->options(fn (): array => self::withholdingTaxOptions())
                                ->searchable()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTax($set))
                                ->columnSpan(['default' => 12, 'md' => 6]),

                            TextInput::make('withholding_tax_base')
                                ->label('Withholding Tax Base')
                                ->numeric()
                                ->prefix('₱')
                                ->live(debounce: 800)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTax($set))
                                ->columnSpan(['default' => 12, 'md' => 6]),

                            Select::make('tax_code')
                                ->label('Tax Code')
                                ->options(fn (): array => self::taxCodeOptions())
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTax($set))
                                ->columnSpan(['default' => 12]),

                            Textarea::make('remarks_ap')
                                ->label('AP Notes')
                                ->columnSpan(['default' => 12]),
                        ]),
                ]),

            Section::make('Summary')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Grid::make()
                        ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->schema([
                            $this->moneyEntry('vatable_sales', 'Vatable Sales'),
                            $this->moneyEntry('vat_exempt', 'VAT Exempt'),
                            $this->moneyEntry('vat_zero_rated', 'VAT Zero Rated'),
                            $this->moneyEntry('vat', 'VAT'),
                            $this->moneyEntry('total', 'Total'),

                            TextInput::make('ewt')
                                ->label('EWT')
                                ->prefix('₱')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('net')
                                ->label('NET')
                                ->prefix('₱')
                                ->readOnly()
                                ->dehydrated(false),

                            TextInput::make('invoice_total')
                                ->label('Overall Invoice Total')
                                ->prefix('₱')
                                ->readOnly()
                                ->dehydrated(false),
                        ]),
                ]),

            // The Medius-style side-by-side comparison.
            ViewField::make('viewer')
                ->hiddenLabel()
                ->view('filament.components.po-gr-viewer')
                ->viewData(fn (): array => ['receipts' => $this->goods_receipts])
                ->columnSpanFull()
                ->dehydrated(false),

            Actions::make([
                Action::make('submit')
                    ->label('Submit to Checker')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(! $this->isViewOnly)
                    ->action('submit'),

                $this->escalateAction(),

                $this->returnToVendorAction(),
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildGoodsReceipts(Invoice $invoice): array
    {
        return $invoice->lines()
            ->with('sapItem.goodReceipt')
            ->get()
            ->groupBy(fn (InvoiceLine $line): string => (string) ($line->sapItem?->goodReceipt?->good_receipt_number ?? $line->purchase_order))
            ->map(function (Collection $lines, string $grNumber): array {
                $first = $lines->first();

                $rows = $lines->map(fn (InvoiceLine $line): array => [
                    'p2p_sap_good_receipt_item_id' => $line->p2p_sap_good_receipt_item_id,
                    'purchase_order' => $line->purchase_order,
                    'purchase_order_item' => $line->purchase_order_item,
                    'material_doc_item' => $line->sapItem?->material_doc_item,
                    'item_name' => $line->item_name,
                    'uom' => $line->uom,
                    'quantity_po' => (int) $line->quantity_po,
                    'quantity_served' => (int) ($line->sapItem?->quantity_served ?? $line->quantity_po),
                    'quantity_billed' => (int) $line->quantity_billed,
                    'unit_cost' => (float) $line->unit_cost,
                    'amount' => round((int) $line->quantity_billed * (float) $line->unit_cost, 2),
                    'escalate' => false,
                    'match' => self::matchFor($line),
                ])->values()->all();

                return [
                    'good_receipt_id' => $first->sapItem?->goodReceipt?->id,
                    'good_receipt_number' => $grNumber,
                    'purchase_order' => $first->purchase_order,
                    'goodReceiptItems' => $rows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: int, 1: int, 2: float}
     */
    #[Computed]
    public function totals(): array
    {
        $items = collect($this->goods_receipts)
            ->flatMap(fn (array $gr): array => $gr['goodReceiptItems'] ?? []);

        return [
            (int) $items->sum(fn (array $i): int => (int) ($i['quantity_po'] ?? 0)),
            (int) $items->sum(fn (array $i): int => (int) ($i['quantity_billed'] ?? 0)),
            round((float) $items->sum(fn (array $i): float => (float) ($i['amount'] ?? 0)), 2),
        ];
    }

    public function submit(): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly) {
            return;
        }

        $this->validate([
            'tax_code' => ['required', 'string'],
        ]);

        $this->persistTax($invoice);

        // The processor hands the submission to the checker. The checker branch
        // itself is blocked (B1), so the status here is the terminal processor step.
        $invoice->forceFill([
            'status' => P2pStatus::SubmittedToChecker,
            'processed' => true,
            'remarks_ap' => $this->remarks_ap,
        ])->save();

        Notification::make()
            ->title('Submitted to checker')
            ->body('The submission has been posted for verification.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function escalate(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly) {
            return;
        }

        $this->escalationReason = isset($data['escalationReason']) ? (string) $data['escalationReason'] : null;
        $this->escalationRemarks = (string) ($data['escalationRemarks'] ?? '');

        $this->validate([
            'escalationReason' => ['required', 'string'],
            'escalationRemarks' => ['required', 'string'],
        ]);

        // Escalation is per line: the processor ticks only the lines in dispute.
        $stateIds = $this->escalatedGoodReceiptStateIds();

        if ($stateIds === []) {
            Notification::make()
                ->title('Select at least one line')
                ->body('Tick the GR line(s) you want to escalate.')
                ->warning()
                ->send();

            return;
        }

        $this->persistTax($invoice);

        app(P2pEscalationRoutingService::class)->start(
            $invoice,
            $this->escalationReason,
            $stateIds,
            auth()->user(),
            $this->escalationRemarks,
        );

        Notification::make()->title('Escalation raised')->success()->send();

        $this->redirectBack();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function returnToVendor(array $data): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null || $this->isViewOnly) {
            return;
        }

        $remarks = (string) ($data['remarks_ap'] ?? '');

        if (trim($remarks) === '') {
            $this->addError('returnRemarks', 'Remarks are required.');

            return;
        }

        $this->persistTax($invoice);

        app(P2pGoodReceiptReturnService::class)->returnToVendor($invoice, auth()->user(), $remarks);

        Notification::make()
            ->title('Returned to vendor')
            ->body('The submission has been returned for correction.')
            ->success()
            ->send();

        $this->redirectBack();
    }

    /**
     * The GR state ids for the lines the processor ticked for escalation.
     *
     * @return array<int, int>
     */
    protected function escalatedGoodReceiptStateIds(): array
    {
        $tickedGrNumbers = collect($this->goods_receipts)
            ->filter(fn (array $gr): bool => collect($gr['goodReceiptItems'] ?? [])
                ->contains(fn (array $item): bool => ! empty($item['escalate'])))
            ->pluck('good_receipt_number')
            ->filter()
            ->values();

        if ($tickedGrNumbers->isEmpty()) {
            return [];
        }

        return GoodReceiptState::query()
            ->whereIn('good_receipt_number', $tickedGrNumbers->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Persist the tax section onto the invoice and recompute the summary.
     */
    protected function persistTax(Invoice $invoice): void
    {
        $invoice->forceFill([
            'withholding_tax_code' => $this->withholding_tax_code,
            'withholding_tax_base' => $this->withholding_tax_base !== null ? (float) $this->withholding_tax_base : null,
            'tax_code' => $this->tax_code,
            'remarks_ap' => $this->remarks_ap,
        ])->save();

        app(P2pTaxSummaryService::class)->recalculate($invoice);
    }

    /**
     * Recompute the summary from the current tax selection. The tax service owns
     * the maths; this only formats it for the read-only inputs.
     */
    protected function recalculateTax(Set $set): void
    {
        $invoice = $this->invoiceModel();

        if ($invoice === null) {
            return;
        }

        // Compute against the in-form selection, not the persisted values.
        $taxCode = $this->tax_code !== null
            ? TaxCode::query()->where('code', $this->tax_code)->first()
            : null;

        $withholding = $this->withholding_tax_code !== null
            ? WithholdingTaxCode::query()->where('code', $this->withholding_tax_code)->first()
            : null;

        [, , $amount] = $this->totals;
        $sales = number_format($amount > 0 ? $amount : (float) $invoice->amount_submitted, 2, '.', '');

        $summary = app(P2pTaxSummaryService::class)->calculateSummary(
            $sales,
            $taxCode,
            (string) ($this->withholding_tax_base ?? $sales),
            $withholding,
        );

        foreach (['vatable_sales', 'vat_exempt', 'vat_zero_rated', 'vat', 'total', 'ewt', 'net'] as $key) {
            $value = number_format((float) ($summary[$key] ?? 0), 2, '.', '');

            $set($key, $value);
            $this->{$key} = $value;
        }
    }

    protected function syncSummaryFromInvoice(Invoice $invoice): void
    {
        foreach (['vatable_sales', 'vat_exempt', 'vat_zero_rated', 'vat', 'total', 'ewt', 'net'] as $key) {
            $this->{$key} = number_format((float) $invoice->{$key}, 2, '.', '');
        }
    }

    protected function moneyEntry(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('₱')
            ->readOnly()
            ->dehydrated(false);
    }

    /**
     * Per-line 3-way detail for the viewer badge: PO vs GR vs invoiced for the
     * PO line this invoice line belongs to.
     *
     * @return array<string, mixed>
     */
    protected static function matchFor(InvoiceLine $line): array
    {
        $service = app(P2pThreeWayMatchService::class);

        return $service->breakdown(
            (string) $line->purchase_order,
            (string) $line->purchase_order_item,
        );
    }

    protected function escalateAction(): Action
    {
        return Action::make('escalate')
            ->label('Escalate')
            ->color('warning')
            ->icon(Heroicon::OutlinedArrowTrendingUp)
            ->visible(! $this->isViewOnly)
            ->schema([
                Select::make('escalationReason')
                    ->label('Reason')
                    ->options(fn (): array => EscalationReason::query()
                        ->active()
                        ->forRole('processor')
                        ->pluck('name', 'code')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('escalationRemarks')
                    ->label('Remarks')
                    ->required(),
            ])
            ->action(fn (array $data) => $this->escalate($data))
            ->modalSubmitActionLabel('Escalate');
    }

    protected function returnToVendorAction(): Action
    {
        return Action::make('returnToVendor')
            ->label('Return to Vendor')
            ->color('danger')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(! $this->isViewOnly)
            ->schema([
                Textarea::make('remarks_ap')
                    ->label('Remarks')
                    ->required(),
            ])
            ->action(fn (array $data) => $this->returnToVendor($data));
    }

    /**
     * @return array<string, string>
     */
    protected static function taxCodeOptions(): array
    {
        return TaxCode::query()
            ->pluck('description', 'code')
            ->map(fn (string $description, string $code): string => $code.' - '.$description)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function withholdingTaxOptions(): array
    {
        return WithholdingTaxCode::query()
            ->pluck('description', 'code')
            ->map(fn (string $description, string $code): string => $code.' - '.$description)
            ->all();
    }

    protected function invoiceModel(): ?Invoice
    {
        if (blank($this->invoice)) {
            return null;
        }

        return $this->loadedInvoice ??= Invoice::query()
            ->with(['vendor', 'company'])
            ->find((int) $this->invoice);
    }

    protected function authorizeAccess(Invoice $invoice): void
    {
        $user = auth()->user();

        if ($user?->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            abort(403, 'You are not authorized to process this record.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->action(fn () => $this->redirectBack()),
        ];
    }

    protected function redirectBack(): void
    {
        $this->redirect(ApprovalResource::getUrl('index'));
    }
}
