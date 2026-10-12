<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and submits P2P invoices (the vendor's "Submit to AP").
 *
 * Billing is by **quantity**, not by cloning GR rows: each line references a SAP GR
 * item and records how much is billed; remaining = mirror quantity − Σ billed. A GR
 * stays billable while it has remaining quantity, so partial invoices need no `-P`
 * clones. See docs/p2p/02-data-model.md §2.5 and 06-workflow-statuses.md §4.
 *
 * Every status change runs inside DB::transaction with a row lock, and writes a
 * p2p_approval_histories row.
 */
class P2pGoodReceiptSubmissionService
{
    /**
     * Statuses from which a vendor may (re)submit.
     */
    private const array SUBMITTABLE = [P2pStatus::Pending, P2pStatus::ReturnedToVendor];

    public function __construct(
        private readonly P2pGoodReceiptStatusService $goodReceiptStatus,
        private readonly P2pPurchaseOrderStatusService $purchaseOrderStatus,
    ) {}

    /**
     * Open a draft submission for one or more pending GRs (vendor "Process").
     *
     * @param  Collection<int, GoodReceiptState>|array<int, int>  $states  GR states, or their ids
     */
    public function start(User $user, Collection|array $states): Invoice
    {
        if (is_array($states)) {
            $states = GoodReceiptState::query()->whereIn('id', $states)->get();
        }

        if ($states->isEmpty()) {
            throw ValidationException::withMessages(['gr_numbers' => 'Select at least one goods receipt.']);
        }

        $companies = $states->pluck('company_id')->filter()->unique();
        $vendors = $states->pluck('vendor_id')->filter()->unique();

        if ($companies->count() > 1 || $vendors->count() > 1) {
            throw ValidationException::withMessages(['gr_numbers' => 'All goods receipts must belong to one company and one vendor.']);
        }

        return Invoice::query()->create([
            'status' => P2pStatus::Pending,
            'user_id' => $user->id,
            'vendor_id' => $vendors->first(),
            'company_id' => $companies->first(),
            'po_numbers' => $states->pluck('purchase_order')->filter()->unique()->values()->all(),
            'gr_numbers' => $states->pluck('good_receipt_number')->filter()->unique()->values()->all(),
        ]);
    }

    /**
     * Submit (or resubmit) selected GR lines to AP Processor.
     *
     * @param  array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: int|float|string}>  $lines
     * @param  array<string, mixed>  $attributes  extra invoice fields (reference_number, invoice_at, attachments, remarks_ap…)
     */
    public function submit(Invoice $invoice, array $lines, User $by, array $attributes = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $lines, $by, $attributes): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($invoice->status, self::SUBMITTABLE, true)) {
                throw ValidationException::withMessages(['status' => 'Only a pending or returned invoice can be submitted.']);
            }

            $lines = array_values(array_filter(
                $lines,
                fn (array $line): bool => (float) ($line['quantity_billed'] ?? 0) > 0,
            ));

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => 'Enter a served quantity on at least one line.']);
            }

            // Replace any previous lines (resubmission after a return).
            $invoice->lines()->delete();

            $quantity = 0;
            $amount = '0.00';

            foreach ($lines as $line) {
                $item = SapGoodReceiptItem::query()->findOrFail($line['p2p_sap_good_receipt_item_id']);
                $billed = (int) $line['quantity_billed'];
                $remaining = $this->remainingQuantityFor($item, $invoice);

                if ($billed > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => "Line {$item->item_name} exceeds the remaining quantity ({$remaining}).",
                    ]);
                }

                $amountLine = bcmul((string) $billed, (string) ($item->unit_cost ?? '0'), 2);

                $invoice->lines()->create([
                    'p2p_sap_good_receipt_item_id' => $item->id,
                    'purchase_order' => $item->purchase_order,
                    'purchase_order_item' => $item->purchase_order_item,
                    'storage_location' => $item->storage_location,
                    'item_name' => $item->item_name,
                    'quantity_po' => $item->quantity_served ?? $item->quantity_po,
                    'quantity_billed' => $billed,
                    'quantity_remaining' => $remaining - $billed,
                    'is_fully_billed' => $remaining === $billed,
                    'uom' => $item->uom,
                    'unit_cost' => $item->unit_cost,
                    'amount' => $amountLine,
                ]);

                $quantity += $billed;
                $amount = bcadd($amount, $amountLine, 2);
            }

            $previous = $invoice->status;

            $invoice->forceFill(array_merge([
                'status' => P2pStatus::SubmittedToAp,
                'submitted_at' => now(),
                'quantity_submitted' => $quantity,
                'amount_submitted' => $amount,
            ], $attributes))->save();

            $this->goodReceiptStatus->recalculateForInvoice($invoice);
            $this->recalculatePurchaseOrders($invoice);

            ApprovalHistory::query()->create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => $by->id,
                'from_status' => $previous,
                'to_status' => P2pStatus::SubmittedToAp,
                'remarks' => $previous === P2pStatus::ReturnedToVendor
                    ? 'Invoice resubmitted by vendor.'
                    : 'Invoice submitted by vendor.',
                'is_visible_to_vendor' => true,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Quantity still billable on a mirror item, excluding the given invoice's own lines.
     */
    public function remainingQuantityFor(SapGoodReceiptItem $item, ?Invoice $invoice = null): int
    {
        $query = InvoiceLine::query()
            ->where('p2p_sap_good_receipt_item_id', $item->id)
            ->whereRelation('invoice', 'status', '!=', P2pStatus::Cancelled->value);

        if ($invoice !== null) {
            $query->where('p2p_invoice_id', '!=', $invoice->id);
        }

        $billed = (int) $query->sum('quantity_billed');
        $total = (int) ($item->quantity_served ?? $item->quantity_po ?? 0);

        return $total - $billed;
    }

    /**
     * Remaining billable quantity per mirror item, in a single query (avoids the
     * N+1 of calling {@see self::remainingQuantityFor()} once per line).
     *
     * @param  Collection<int, SapGoodReceiptItem>  $items
     * @return array<int, int> remaining quantity keyed by mirror item id
     */
    public function remainingQuantitiesFor(Collection $items, ?Invoice $invoice = null): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $query = InvoiceLine::query()
            ->whereIn('p2p_sap_good_receipt_item_id', $items->pluck('id'))
            ->whereRelation('invoice', 'status', '!=', P2pStatus::Cancelled->value);

        if ($invoice !== null) {
            $query->where('p2p_invoice_id', '!=', $invoice->id);
        }

        $billedByItem = $query
            ->groupBy('p2p_sap_good_receipt_item_id')
            ->selectRaw('p2p_sap_good_receipt_item_id, SUM(quantity_billed) as billed')
            ->pluck('billed', 'p2p_sap_good_receipt_item_id');

        return $items
            ->mapWithKeys(function (SapGoodReceiptItem $item) use ($billedByItem): array {
                $total = (int) ($item->quantity_served ?? $item->quantity_po ?? 0);

                return [$item->id => $total - (int) ($billedByItem[$item->id] ?? 0)];
            })
            ->all();
    }

    protected function recalculatePurchaseOrders(Invoice $invoice): void
    {
        SapPurchaseOrder::query()
            ->whereIn('purchase_order', $invoice->po_numbers ?? [])
            ->get()
            ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));
    }
}
