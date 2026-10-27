<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and submits P2P invoices (the vendor's "Submit to AP").
 *
 * Billing is by **quantity**: each line references a SAP GR item and records how much
 * is billed; remaining = mirror quantity − Σ billed. When the vendor submits only some
 * of a GR's items, the unselected items move to a `-P{n}` clone so they stay pending
 * while the selected items are submitted on the real SAP GR number.
 * See docs/p2p/02-data-model.md §2.5 and 06-workflow-statuses.md §4.
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

    /**
     * Invoice-match fields a submitted line may carry (see P2pInvoiceMatcher).
     */
    public const array MATCH_FIELDS = [
        'invoice_reference', 'invoice_unit_price', 'invoice_currency', 'match_status', 'match_reasons',
        'is_invoice_verified', 'is_quantity_edited',
    ];

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

        if ($states->contains(fn (GoodReceiptState $state): bool => ! $state->status->isEditableByVendor())) {
            throw ValidationException::withMessages([
                'gr_numbers' => 'Only pending or returned goods receipts can be processed.',
            ]);
        }

        $companies = $states->pluck('company_id')->filter()->unique();
        $vendors = $states->pluck('vendor_id')->filter()->unique();
        $currencies = $this->currenciesForPurchaseOrders(
            $states->pluck('purchase_order')->filter()->unique()->values()->all(),
        );

        if ($companies->count() > 1) {
            throw ValidationException::withMessages(['gr_numbers' => 'All goods receipts must belong to one company.']);
        }

        if ($vendors->count() > 1) {
            throw ValidationException::withMessages(['gr_numbers' => 'All goods receipts must belong to one vendor.']);
        }

        if ($currencies->count() > 1) {
            throw ValidationException::withMessages([
                'gr_numbers' => 'All goods receipts must use one currency ('.$currencies->implode(', ').').',
            ]);
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
     * The GR states that own the given SAP GR items.
     *
     * @param  array<int, int>  $itemIds
     * @return Collection<int, GoodReceiptState>
     */
    public function statesForItems(array $itemIds): Collection
    {
        $receiptIds = SapGoodReceiptItem::query()
            ->whereKey($itemIds)
            ->pluck('p2p_sap_good_receipt_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($receiptIds === []) {
            return collect();
        }

        return GoodReceiptState::query()
            ->whereIn('p2p_sap_good_receipt_id', $receiptIds)
            ->get();
    }

    /**
     * Split off the items the vendor did not select, so a partial submission or
     * escalation leaves them pending on a `-P{n}` GR instead of advancing the whole GR.
     *
     * The selected items stay on their GR (the invoice keeps the real SAP GR number);
     * the unselected items that still have billable quantity move to a clone GR.
     *
     * @param  array<int, int>  $selectedItemIds
     */
    public function splitForSelectedItems(array $selectedItemIds, ?Invoice $invoice = null): void
    {
        $selectedItemIds = array_values(array_unique(array_map('intval', $selectedItemIds)));

        if ($selectedItemIds === []) {
            return;
        }

        $selectedItems = SapGoodReceiptItem::query()
            ->with('goodReceipt')
            ->whereKey($selectedItemIds)
            ->get();

        foreach ($selectedItems->groupBy('p2p_sap_good_receipt_id') as $items) {
            $receipt = $items->first()?->goodReceipt;

            if ($receipt === null) {
                continue;
            }

            $state = GoodReceiptState::query()
                ->where('p2p_sap_good_receipt_id', $receipt->id)
                ->first();

            if ($state === null) {
                continue;
            }

            $this->splitOffUnselectedItems($state, $items->pluck('id')->all(), $invoice);
        }
    }

    /**
     * @param  array<int, int>  $selectedItemIds
     */
    protected function splitOffUnselectedItems(GoodReceiptState $state, array $selectedItemIds, ?Invoice $invoice): void
    {
        $receipt = $state->sapGoodReceipt;
        $grNumber = (string) $state->good_receipt_number;

        if ($receipt === null || $grNumber === '') {
            return;
        }

        $items = $receipt->items()->get();
        $unselected = $items->whereNotIn('id', $selectedItemIds)->values();

        if ($unselected->isEmpty()) {
            return;
        }

        $remaining = $this->remainingQuantitiesFor($items, $invoice);

        $billable = $unselected
            ->filter(fn (SapGoodReceiptItem $item): bool => ($remaining[$item->id] ?? 0) > 0)
            ->values();

        if ($billable->isEmpty()) {
            return;
        }

        $partialNumber = $this->nextPartialGrNumber($grNumber);

        $partialReceipt = SapGoodReceipt::query()->create([
            'purchase_order' => $receipt->purchase_order,
            'company_id' => $receipt->company_id,
            'vendor_id' => $receipt->vendor_id,
            'good_receipt_number' => $partialNumber,
            'reference_number' => $receipt->reference_number,
            'amount_total' => $billable->sum(fn (SapGoodReceiptItem $item): float => $this->itemAmount($item)),
            'due_at' => $receipt->due_at,
            'upload_at' => $receipt->upload_at,
            'date_at' => $receipt->date_at,
            'delivery_type_id' => $receipt->delivery_type_id,
        ]);

        SapGoodReceiptItem::query()
            ->whereKey($billable->pluck('id')->all())
            ->update(['p2p_sap_good_receipt_id' => $partialReceipt->id]);

        $partialState = GoodReceiptState::query()->create([
            'p2p_sap_good_receipt_id' => $partialReceipt->id,
            'purchase_order' => $state->purchase_order,
            'good_receipt_number' => $partialNumber,
            'company_id' => $state->company_id,
            'vendor_id' => $state->vendor_id,
            'status' => P2pStatus::Pending,
        ]);

        $state->unsetRelation('sapGoodReceipt');

        $this->syncStateTotals($state);
        $this->syncStateTotals($partialState);
    }

    /**
     * Keep a GR's cached quantity/amount totals in step with the items it still owns
     * after a split.
     */
    protected function syncStateTotals(GoodReceiptState $state): void
    {
        $items = $state->sapGoodReceipt?->items()->get() ?? collect();

        $state->forceFill([
            'quantity_total' => (int) round((float) $items->sum('quantity_po')),
            'quantity_served' => (int) round((float) $items->sum(
                fn (SapGoodReceiptItem $item): float => (float) ($item->quantity_served ?? $item->quantity_po ?? 0),
            )),
            'amount_total' => $items->sum(fn (SapGoodReceiptItem $item): float => $this->itemAmount($item)),
        ])->save();
    }

    protected function itemAmount(SapGoodReceiptItem $item): float
    {
        if ($item->amount !== null) {
            return (float) $item->amount;
        }

        return (float) ($item->quantity_served ?? $item->quantity_po ?? 0) * (float) ($item->unit_cost ?? 0);
    }

    /**
     * The next free legacy-style partial number (`{base}-P1`, `{base}-P2`, …).
     */
    protected function nextPartialGrNumber(string $grNumber): string
    {
        $base = preg_replace('/-P\d+$/', '', $grNumber) ?: $grNumber;

        $suffix = GoodReceiptState::query()
            ->where('good_receipt_number', 'LIKE', $base.'-P%')
            ->pluck('good_receipt_number')
            ->map(fn (?string $number): int => (int) preg_replace('/^.*-P(\d+)$/', '$1', (string) $number))
            ->max() ?? 0;

        return $base.'-P'.($suffix + 1);
    }

    /**
     * Submit (or resubmit) selected GR lines to AP Processor.
     *
     * @param  array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: int|float|string, invoice_reference?: ?string, invoice_unit_price?: ?float, invoice_currency?: ?string, match_status?: ?string, match_reasons?: list<string>, is_invoice_verified?: bool, is_quantity_edited?: bool}>  $lines
     *                                                                                                                                                                                                                                                                                                             lines may carry their match against the vendor's invoice (Read invoice with AI)
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

            // Leave the items the vendor did not tick on their own pending GR; the
            // selected items keep the real SAP GR number for the invoice.
            $this->splitForSelectedItems(
                array_map(fn (array $line): int => (int) $line['p2p_sap_good_receipt_item_id'], $lines),
                $invoice,
            );

            $items = SapGoodReceiptItem::query()
                ->with('goodReceipt')
                ->whereIn('id', array_map(
                    fn (array $line): int => (int) $line['p2p_sap_good_receipt_item_id'],
                    $lines,
                ))
                ->get();

            $this->assertLinesMatchInvoice($invoice, $items);

            $itemsById = $items->keyBy('id');

            // Replace any previous lines (resubmission after a return).
            $invoice->lines()->delete();

            $quantity = 0;
            $amount = '0.00';

            foreach ($lines as $line) {
                $item = $itemsById->get((int) $line['p2p_sap_good_receipt_item_id'])
                    ?? SapGoodReceiptItem::query()->findOrFail($line['p2p_sap_good_receipt_item_id']);
                $billed = (int) $line['quantity_billed'];
                $remaining = $this->remainingQuantityFor($item, $invoice);

                if ($billed > $remaining) {
                    $label = $item->item_description ?: $item->item_name ?: $item->purchase_order_item;

                    throw ValidationException::withMessages([
                        'lines' => "Line {$item->purchase_order_item} — {$label} exceeds the remaining quantity ({$remaining}).",
                    ]);
                }

                $amountLine = bcmul((string) $billed, (string) ($item->unit_cost ?? '0'), 2);

                $invoice->lines()->create([
                    'p2p_sap_good_receipt_item_id' => $item->id,
                    'purchase_order' => $item->purchase_order,
                    'purchase_order_item' => $item->purchase_order_item,
                    'storage_location' => $item->storage_location,
                    'item_name' => $item->item_name,
                    'item_description' => $item->item_description,
                    'quantity_po' => $item->quantity_served ?? $item->quantity_po,
                    'quantity_billed' => $billed,
                    'quantity_remaining' => $remaining - $billed,
                    'is_fully_billed' => $remaining === $billed,
                    'uom' => $item->uom,
                    'unit_cost' => $item->unit_cost,
                    'amount' => $amountLine,
                    ...Arr::only($line, self::MATCH_FIELDS),
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
     * Guard against mixing goods receipts from another company, vendor or currency
     * into a request. The GR selection screen already limits this, but submit() is
     * callable directly, so the rule lives here too.
     *
     * @param  Collection<int, SapGoodReceiptItem>  $items
     */
    protected function assertLinesMatchInvoice(Invoice $invoice, Collection $items): void
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Select at least one goods-receipt line.']);
        }

        $poNumbers = $items->pluck('purchase_order')->filter()->unique();
        $grNumbers = $items
            ->map(fn (SapGoodReceiptItem $item): ?string => $item->goodReceipt?->good_receipt_number)
            ->filter()
            ->unique();

        if (collect($invoice->po_numbers ?? [])->filter()->isNotEmpty()
            && $poNumbers->diff(collect($invoice->po_numbers))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'All lines must belong to the purchase orders of this request.',
            ]);
        }

        if (collect($invoice->gr_numbers ?? [])->filter()->isNotEmpty()
            && $grNumbers->diff(collect($invoice->gr_numbers))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'All lines must belong to the goods receipts of this request.',
            ]);
        }

        $companies = $items
            ->map(fn (SapGoodReceiptItem $item): ?int => $item->goodReceipt?->company_id)
            ->push($invoice->company_id)
            ->filter()
            ->unique();

        $vendors = $items
            ->map(fn (SapGoodReceiptItem $item): ?int => $item->goodReceipt?->vendor_id)
            ->push($invoice->vendor_id)
            ->filter()
            ->unique();

        if ($companies->count() > 1) {
            throw ValidationException::withMessages(['lines' => 'All lines must belong to one company.']);
        }

        if ($vendors->count() > 1) {
            throw ValidationException::withMessages(['lines' => 'All lines must belong to one vendor.']);
        }

        $currencies = $this->currenciesForPurchaseOrders($poNumbers->values()->all());

        if ($currencies->count() > 1) {
            throw ValidationException::withMessages([
                'lines' => 'All lines must use one currency ('.$currencies->implode(', ').').',
            ]);
        }
    }

    /**
     * Distinct currencies of the purchase orders behind the given PO numbers.
     *
     * @param  array<int, string|null>  $purchaseOrderNumbers
     * @return Collection<int, string>
     */
    protected function currenciesForPurchaseOrders(array $purchaseOrderNumbers): Collection
    {
        if ($purchaseOrderNumbers === []) {
            return collect();
        }

        return SapPurchaseOrder::query()
            ->whereIn('purchase_order', $purchaseOrderNumbers)
            ->pluck('currency_key')
            ->map(fn (?string $currency): string => strtoupper(trim((string) $currency)))
            ->filter()
            ->unique()
            ->values();
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
