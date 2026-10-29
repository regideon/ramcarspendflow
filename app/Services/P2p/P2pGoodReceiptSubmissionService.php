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
use App\Support\GoodReceiptNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and submits P2P invoices (the vendor's "Submit to AP").
 *
 * The base GR is a permanent pending container and is never submitted. Every
 * submit/escalate clones the selected quantities onto a local `-P{n}` split, so
 * the base keeps the remainder (and can receive it back when a split is cancelled).
 *
 * Billing is by **quantity**: each line references a split clone and records how
 * much is billed; remaining = mirror quantity − Σ billed. See
 * docs/p2p/02-data-model.md §2.5 and 06-workflow-statuses.md §4.
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
     * Create the split(s) for a submission. Returns the mirror item id to bill for
     * every submitted line (the split clone where one was created).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    public function splitForSubmission(Invoice $invoice, array $lines): array
    {
        if ($invoice->status === P2pStatus::ReturnedToVendor) {
            return $this->rebalanceReturnedInvoice($invoice, $lines);
        }

        $allocations = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['p2p_sap_good_receipt_item_id'] ?? 0);
            $quantity = (int) ($line['quantity_billed'] ?? 0);

            if ($itemId > 0 && $quantity > 0) {
                $allocations[$itemId] = ($allocations[$itemId] ?? 0) + $quantity;
            }
        }

        [$map] = $this->splitAllocations($invoice, $allocations);

        return $map;
    }

    /**
     * Create the split(s) for an escalation. Whole remaining quantities move to
     * `-P{n}`; returns the split states to escalate.
     *
     * @param  array<int, int>  $itemIds
     * @return Collection<int, GoodReceiptState>
     */
    public function splitForEscalation(Invoice $invoice, array $itemIds): Collection
    {
        $items = SapGoodReceiptItem::query()
            ->with('goodReceipt')
            ->whereKey(array_values(array_unique(array_map('intval', $itemIds))))
            ->get();

        $allocations = $items
            ->mapWithKeys(fn (SapGoodReceiptItem $item): array => [
                $item->id => max(0, $this->remainingQuantityFor($item, $invoice)),
            ])
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();

        [, $states] = $this->splitAllocations($invoice, $allocations);

        if ($states->isEmpty()) {
            return $this->statesForItems($itemIds);
        }

        return $states;
    }

    /**
     * @param  array<int, int>  $allocations  mirror item id => quantity to put on the split
     * @return array{0: array<int, int>, 1: Collection<int, GoodReceiptState>}
     */
    protected function splitAllocations(Invoice $invoice, array $allocations): array
    {
        if ($allocations === []) {
            return [[], collect()];
        }

        $items = SapGoodReceiptItem::query()
            ->with('goodReceipt')
            ->whereKey(array_keys($allocations))
            ->get();

        $map = [];
        $states = [];
        $bases = [];
        $splitStates = [];
        $numberMap = [];

        foreach ($items as $item) {
            $quantity = (int) ($allocations[$item->id] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $receipt = $item->goodReceipt;

            if ($receipt === null) {
                $map[$item->id] = $item->id;

                continue;
            }

            $baseState = GoodReceiptState::query()
                ->where('p2p_sap_good_receipt_id', $receipt->id)
                ->first();

            if ($baseState === null) {
                $map[$item->id] = $item->id;

                continue;
            }

            // Already on a split (returned invoice / escalation retry): keep it.
            if (GoodReceiptNumber::isSplit($baseState->good_receipt_number)) {
                $map[$item->id] = $item->id;
                $states[$baseState->id] = $baseState;

                continue;
            }

            $remaining = $this->remainingQuantityFor($item, $invoice);

            if ($quantity > $remaining) {
                throw ValidationException::withMessages([
                    'lines' => $this->overQuantityMessage($item, $remaining),
                ]);
            }

            $splitState = $splitStates[$baseState->id] ?? null;

            if ($splitState === null) {
                $splitState = $this->createSplitState($baseState);

                if ($splitState === null) {
                    $map[$item->id] = $item->id;

                    continue;
                }

                $splitStates[$baseState->id] = $splitState;
                $numberMap[(string) $baseState->good_receipt_number] = (string) $splitState->good_receipt_number;
            }

            $clone = $this->cloneItemToSplit($item, $splitState, $quantity);

            $map[$item->id] = $clone->id;
            $states[$splitState->id] = $splitState;
            $bases[$baseState->id] = $baseState;
        }

        if ($numberMap !== []) {
            $this->reconcileInvoiceGrNumbers($invoice, $numberMap);
        }

        foreach ($bases as $baseState) {
            $this->refreshStateTotals($baseState);
            $this->goodReceiptStatus->recalculate($baseState);
        }

        foreach ($states as $splitState) {
            $this->refreshStateTotals($splitState);
        }

        return [$map, collect($states)->values()];
    }

    protected function createSplitState(GoodReceiptState $baseState): ?GoodReceiptState
    {
        /** @var GoodReceiptState $baseState */
        $baseState = GoodReceiptState::query()->whereKey($baseState->getKey())->lockForUpdate()->firstOrFail();
        $baseNumber = (string) $baseState->good_receipt_number;
        $receipt = $baseState->sapGoodReceipt;

        if ($receipt === null || $baseNumber === '' || GoodReceiptNumber::isSplit($baseNumber)) {
            return null;
        }

        $splitNumber = $this->nextPartialGrNumber($baseNumber);

        $splitReceipt = SapGoodReceipt::query()->create([
            'purchase_order' => $receipt->purchase_order,
            'company_id' => $receipt->company_id,
            'vendor_id' => $receipt->vendor_id,
            'good_receipt_number' => $splitNumber,
            'reference_number' => $receipt->reference_number,
            'amount_total' => 0,
            'due_at' => $receipt->due_at,
            'upload_at' => $receipt->upload_at,
            'date_at' => $receipt->date_at,
            'delivery_type_id' => $receipt->delivery_type_id,
        ]);

        $splitState = GoodReceiptState::query()->create([
            'p2p_sap_good_receipt_id' => $splitReceipt->id,
            'purchase_order' => $baseState->purchase_order,
            'good_receipt_number' => $splitNumber,
            'company_id' => $baseState->company_id,
            'vendor_id' => $baseState->vendor_id,
            'status' => P2pStatus::Pending,
        ]);

        $splitState->setRelation('sapGoodReceipt', $splitReceipt);

        return $splitState;
    }

    protected function cloneItemToSplit(
        SapGoodReceiptItem $item,
        GoodReceiptState $splitState,
        int $quantity,
    ): SapGoodReceiptItem {
        $baseQuantity = (int) ($item->quantity_served ?? $item->quantity_po ?? 0);

        if ($quantity > $baseQuantity) {
            throw ValidationException::withMessages([
                'lines' => $this->overQuantityMessage($item, $baseQuantity),
            ]);
        }

        $clone = SapGoodReceiptItem::query()->create([
            'p2p_sap_good_receipt_id' => $splitState->p2p_sap_good_receipt_id,
            'purchase_order' => $item->purchase_order,
            'purchase_order_item' => $item->purchase_order_item,
            'item_numbering' => $item->item_numbering,
            'item_id' => $item->item_id,
            'material_doc_year' => $item->material_doc_year,
            'material_doc_item' => $item->material_doc_item,
            'storage_location' => $item->storage_location,
            'item_name' => $item->item_name,
            'item_description' => $item->item_description,
            'quantity_po' => $item->quantity_po,
            'quantity_served' => $quantity,
            'quantity_split_out' => 0,
            'split_from_item_id' => $item->id,
            'uom' => $item->uom,
            'gr' => $item->gr,
            'unit_cost' => $item->unit_cost,
            'net_price' => $item->net_price,
            'price_unit' => $item->price_unit,
            'currency' => $item->currency,
            'amount' => $this->cloneAmount($item, $quantity, $baseQuantity),
            'quantity_po_balance' => $item->quantity_po_balance,
        ]);

        $item->forceFill([
            'quantity_served' => $baseQuantity - $quantity,
            'quantity_split_out' => (int) ($item->quantity_split_out ?? 0) + $quantity,
        ])->save();

        return $clone;
    }

    protected function cloneAmount(SapGoodReceiptItem $item, int $quantity, int $baseQuantity): ?string
    {
        if ($item->unit_cost !== null) {
            return bcmul((string) $quantity, (string) $item->unit_cost, 2);
        }

        if ($item->amount !== null && $baseQuantity > 0) {
            return bcmul((string) $item->amount, (string) ($quantity / $baseQuantity), 2);
        }

        return null;
    }

    /**
     * The returned invoice may only edit the split it was raised on. Reconcile the
     * new quantities with the existing split clones, and return removed items to
     * the base GR.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    protected function rebalanceReturnedInvoice(Invoice $invoice, array $lines): array
    {
        $selected = [];

        foreach ($lines as $line) {
            $selected[(int) $line['p2p_sap_good_receipt_item_id']] = (int) ($line['quantity_billed'] ?? 0);
        }

        $existing = $invoice->lines()->with('sapItem.splitFrom', 'sapItem.goodReceipt')->get();
        $map = [];
        $bases = [];
        $splits = [];

        foreach ($existing as $line) {
            $item = $line->sapItem;

            if ($item === null) {
                continue;
            }

            $map[$item->id] = $item->id;
            $baseState = $this->baseStateForSplitItem($item);

            if (! array_key_exists($item->id, $selected) || $selected[$item->id] <= 0) {
                if ($this->restoreCloneToBase($item, $baseState)) {
                    if ($baseState !== null) {
                        $bases[$baseState->id] = $baseState;
                    }

                    if ($splitState = $this->stateForReceipt($item->goodReceipt)) {
                        $splits[$splitState->id] = $splitState;
                    }
                }

                continue;
            }

            $this->resizeSplitClone($item, $selected[$item->id], $baseState);

            if ($baseState !== null) {
                $bases[$baseState->id] = $baseState;
            }

            if ($splitState = $this->stateForReceipt($item->goodReceipt)) {
                $splits[$splitState->id] = $splitState;
            }
        }

        foreach ($bases as $baseState) {
            $this->refreshStateTotals($baseState);
            $this->goodReceiptStatus->recalculate($baseState);
        }

        foreach ($splits as $splitState) {
            $this->refreshStateTotals($splitState);
        }

        $this->closeEmptySplits($invoice);

        return $map;
    }

    protected function resizeSplitClone(
        SapGoodReceiptItem $clone,
        int $newQuantity,
        ?GoodReceiptState $baseState,
    ): void {
        $base = $clone->splitFrom;

        if ($base === null || $baseState === null || $baseState->reversed_at !== null) {
            return;
        }

        $oldQuantity = (int) ($clone->quantity_served ?? 0);
        $delta = $newQuantity - $oldQuantity;

        if ($delta === 0) {
            return;
        }

        if ($delta > (int) ($base->quantity_served ?? 0)) {
            throw ValidationException::withMessages([
                'lines' => $this->overQuantityMessage($base, (int) ($base->quantity_served ?? 0)),
            ]);
        }

        $base->forceFill([
            'quantity_served' => (int) ($base->quantity_served ?? 0) - $delta,
            'quantity_split_out' => max(0, (int) ($base->quantity_split_out ?? 0) + $delta),
        ])->save();

        $clone->forceFill(['quantity_served' => $newQuantity])->save();
    }

    /**
     * Put a split clone's quantity back on its base item. Returns false when the
     * base GR is gone or reversed; the item then stays on the split.
     */
    protected function restoreCloneToBase(
        SapGoodReceiptItem $clone,
        ?GoodReceiptState $baseState = null,
    ): bool {
        if ($clone->merged_at !== null) {
            return false;
        }

        if ($clone->split_from_item_id === null) {
            return $this->restoreMovedItem($clone, $baseState);
        }

        $baseItem = SapGoodReceiptItem::query()->whereKey($clone->split_from_item_id)->first();

        if ($baseItem === null) {
            return false;
        }

        $baseState ??= GoodReceiptState::query()
            ->where('p2p_sap_good_receipt_id', $baseItem->p2p_sap_good_receipt_id)
            ->first();

        if ($baseState === null || $baseState->reversed_at !== null) {
            return false;
        }

        $quantity = (int) ($clone->quantity_served ?? 0);

        $baseItem->forceFill([
            'quantity_served' => (int) ($baseItem->quantity_served ?? 0) + $quantity,
            'quantity_split_out' => max(0, (int) ($baseItem->quantity_split_out ?? 0) - $quantity),
        ])->save();

        $clone->forceFill(['merged_at' => now()])->save();

        return true;
    }

    /**
     * Compatibility for rows created by the earlier implementation, which moved
     * whole items to the split instead of cloning them.
     */
    protected function restoreMovedItem(SapGoodReceiptItem $item, ?GoodReceiptState $baseState = null): bool
    {
        $baseNumber = GoodReceiptNumber::base($item->goodReceipt?->good_receipt_number);
        $baseReceipt = SapGoodReceipt::query()->where('good_receipt_number', $baseNumber)->first();

        if ($baseReceipt === null || $baseState?->reversed_at !== null) {
            return false;
        }

        $duplicate = SapGoodReceiptItem::query()
            ->where('p2p_sap_good_receipt_id', $baseReceipt->id)
            ->where('material_doc_year', $item->material_doc_year)
            ->where('material_doc_item', $item->material_doc_item)
            ->whereKeyNot($item->id)
            ->exists();

        if ($duplicate) {
            return false;
        }

        $item->forceFill(['p2p_sap_good_receipt_id' => $baseReceipt->id])->save();

        return true;
    }

    protected function stateForReceipt(?SapGoodReceipt $receipt): ?GoodReceiptState
    {
        if ($receipt === null) {
            return null;
        }

        return GoodReceiptState::query()
            ->where('p2p_sap_good_receipt_id', $receipt->id)
            ->first();
    }

    protected function baseStateForSplitItem(SapGoodReceiptItem $item): ?GoodReceiptState
    {
        if ($item->split_from_item_id === null) {
            $baseNumber = GoodReceiptNumber::base($item->goodReceipt?->good_receipt_number);

            return GoodReceiptState::query()->where('good_receipt_number', $baseNumber)->first();
        }

        $baseItemReceiptId = SapGoodReceiptItem::query()
            ->whereKey($item->split_from_item_id)
            ->value('p2p_sap_good_receipt_id');

        if ($baseItemReceiptId === null) {
            return null;
        }

        return GoodReceiptState::query()
            ->where('p2p_sap_good_receipt_id', $baseItemReceiptId)
            ->first();
    }

    /**
     * Remove split numbers whose items were all returned to the base, so a
     * resubmitted invoice only names the splits it still uses.
     */
    protected function closeEmptySplits(Invoice $invoice): void
    {
        $numbers = collect($invoice->gr_numbers ?? [])->filter(fn (?string $number): bool => GoodReceiptNumber::isSplit($number));
        $kept = array_values($invoice->gr_numbers ?? []);

        foreach ($numbers as $number) {
            $state = GoodReceiptState::query()->where('good_receipt_number', $number)->first();

            if ($state === null || $state->merged_at !== null) {
                $kept = array_values(array_filter($kept, fn (string $value): bool => $value !== $number));

                continue;
            }

            $hasItems = $state->sapGoodReceipt?->items()->whereNull('merged_at')->exists() ?? false;

            if ($hasItems) {
                continue;
            }

            $state->forceFill([
                'status' => P2pStatus::Cancelled,
                'merged_at' => now(),
                'merged_into_good_receipt_number' => GoodReceiptNumber::base($number),
            ])->save();

            $kept = array_values(array_filter($kept, fn (string $value): bool => $value !== $number));
        }

        if ($kept !== array_values($invoice->gr_numbers ?? [])) {
            $invoice->forceFill(['gr_numbers' => $kept])->save();
        }
    }

    /**
     * Vendor cancellation: return every split's items to its base GR. This is the
     * only path that merges; returns, rejects, reversals and re-GRs do not.
     */
    public function mergeCancelledSplits(Invoice $invoice): void
    {
        $numbers = collect($invoice->gr_numbers ?? [])->filter(fn (?string $number): bool => GoodReceiptNumber::isSplit($number));
        $bases = [];

        foreach ($numbers as $number) {
            $splitState = GoodReceiptState::query()
                ->with('sapGoodReceipt.items')
                ->where('good_receipt_number', $number)
                ->first();

            if ($splitState === null || $splitState->merged_at !== null) {
                continue;
            }

            $baseState = GoodReceiptState::query()
                ->where('good_receipt_number', GoodReceiptNumber::base($number))
                ->first();

            if ($baseState === null || $baseState->reversed_at !== null) {
                continue;
            }

            $items = $splitState->sapGoodReceipt?->items()->whereNull('merged_at')->get() ?? collect();

            if ($items->isEmpty()) {
                $this->markSplitMerged($splitState, $baseState);

                continue;
            }

            $hasOtherInvoice = InvoiceLine::query()
                ->whereIn('p2p_sap_good_receipt_item_id', $items->pluck('id'))
                ->where('p2p_invoice_id', '!=', $invoice->id)
                ->whereRelation('invoice', 'status', '!=', P2pStatus::Cancelled->value)
                ->exists();

            if ($hasOtherInvoice) {
                continue;
            }

            $restored = true;

            foreach ($items as $item) {
                if (! $this->restoreCloneToBase($item, $baseState)) {
                    $restored = false;
                }
            }

            $bases[$baseState->id] = $baseState;
            $this->refreshStateTotals($splitState);

            if ($restored && ! $splitState->sapGoodReceipt?->items()->whereNull('merged_at')->exists()) {
                $this->markSplitMerged($splitState, $baseState);
            }
        }

        foreach ($bases as $baseState) {
            $this->refreshStateTotals($baseState);
            $this->goodReceiptStatus->recalculate($baseState);
        }

        $this->recalculatePurchaseOrders($invoice);
    }

    protected function markSplitMerged(GoodReceiptState $splitState, GoodReceiptState $baseState): void
    {
        $splitState->forceFill([
            'status' => P2pStatus::Cancelled,
            'merged_at' => now(),
            'merged_into_good_receipt_number' => $baseState->good_receipt_number,
        ])->save();
    }

    /**
     * Submit (or resubmit) selected GR lines to AP Processor.
     *
     * @param  array<int, array{p2p_sap_good_receipt_item_id: int, quantity_billed: int|float|string, invoice_reference?: ?string, invoice_unit_price?: ?float, invoice_currency?: ?string, match_status?: ?string, match_reasons?: list<string>, is_invoice_verified?: bool, is_quantity_edited?: bool}>  $lines
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

            $map = $this->splitForSubmission($invoice, $lines);

            $lines = array_map(function (array $line) use ($map): array {
                $itemId = (int) $line['p2p_sap_good_receipt_item_id'];
                $line['p2p_sap_good_receipt_item_id'] = $map[$itemId] ?? $itemId;

                return $line;
            }, $lines);

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
                    throw ValidationException::withMessages([
                        'lines' => $this->overQuantityMessage($item, $remaining),
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

    /**
     * Keep a GR's cached quantity/amount totals in step with the items it owns.
     */
    protected function refreshStateTotals(GoodReceiptState $state): void
    {
        $receipt = $state->sapGoodReceipt;

        if ($receipt === null) {
            return;
        }

        $items = $receipt->items()->whereNull('merged_at')->get();
        $amount = $items->sum(fn (SapGoodReceiptItem $item): float => $this->itemAmount($item));

        $state->forceFill([
            'quantity_total' => (int) round((float) $items->sum('quantity_po')),
            'quantity_served' => (int) round((float) $items->sum(
                fn (SapGoodReceiptItem $item): float => (float) ($item->quantity_served ?? $item->quantity_po ?? 0),
            )),
            'amount_total' => $amount,
        ])->save();

        if (GoodReceiptNumber::isSplit($state->good_receipt_number)) {
            $receipt->forceFill(['amount_total' => $amount])->save();
        }
    }

    protected function itemAmount(SapGoodReceiptItem $item): float
    {
        $quantity = (int) ($item->quantity_served ?? $item->quantity_po ?? 0);

        if ($item->unit_cost !== null) {
            return $quantity * (float) $item->unit_cost;
        }

        return (float) ($item->amount ?? 0);
    }

    /**
     * The next free legacy-style split number (`{base}-P1`, `{base}-P2`, …). Split
     * headers are never deleted, so a cancelled number is never reused.
     */
    protected function nextPartialGrNumber(string $base): string
    {
        $suffix = SapGoodReceipt::query()
            ->where('good_receipt_number', 'LIKE', $base.'-P%')
            ->pluck('good_receipt_number')
            ->map(fn (string $number): int => GoodReceiptNumber::suffix($number) ?? 0)
            ->max() ?? 0;

        return $base.'-P'.($suffix + 1);
    }

    /**
     * Replace base GR numbers with the split numbers on the invoice.
     *
     * @param  array<string, string>  $replacements  base number => split number
     */
    protected function reconcileInvoiceGrNumbers(Invoice $invoice, array $replacements): void
    {
        $numbers = array_values($invoice->gr_numbers ?? []);

        foreach ($numbers as $index => $number) {
            if (isset($replacements[$number])) {
                $numbers[$index] = $replacements[$number];
            }
        }

        foreach ($replacements as $split) {
            if (! in_array($split, $numbers, true)) {
                $numbers[] = $split;
            }
        }

        $invoice->forceFill(['gr_numbers' => array_values(array_unique($numbers))])->save();
    }

    protected function overQuantityMessage(SapGoodReceiptItem $item, int $remaining): string
    {
        $label = $item->item_description ?: $item->item_name ?: $item->purchase_order_item;

        return "Line {$item->purchase_order_item} — {$label} exceeds the remaining quantity ({$remaining}).";
    }

    protected function recalculatePurchaseOrders(Invoice $invoice): void
    {
        SapPurchaseOrder::query()
            ->whereIn('purchase_order', $invoice->po_numbers ?? [])
            ->get()
            ->each(fn (SapPurchaseOrder $po) => $this->purchaseOrderStatus->recalculate($po));
    }
}
