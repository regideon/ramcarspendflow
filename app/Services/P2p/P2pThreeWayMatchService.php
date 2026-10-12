<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;
use Illuminate\Support\Collection;

/**
 * PO vs GR vs invoice reconciliation for a line, shown as a badge in the UI.
 */
class P2pThreeWayMatchService
{
    /**
     * Compare the billed quantity to what was received and ordered.
     */
    public function match(SapGoodReceiptItem $item, InvoiceLine $line): P2pThreeWayMatchStatus
    {
        return $this->statusFor(
            (int) ($item->quantity_po ?? 0),
            (int) ($item->quantity_served ?? $item->quantity_po ?? 0),
            (int) $line->quantity_billed,
        );
    }

    /**
     * Classify ordered / received / invoiced quantities.
     */
    public function statusFor(int $ordered, int $received, int $invoiced): P2pThreeWayMatchStatus
    {
        // Invoiced more than received — a real mismatch (over-delivery, wrong qty).
        if ($invoiced > $received) {
            return P2pThreeWayMatchStatus::NoMatch;
        }

        // Invoice reconciles with the receipt, but the PO still has an open quantity.
        if ($received < $ordered) {
            return P2pThreeWayMatchStatus::OpenPo;
        }

        return P2pThreeWayMatchStatus::Matched;
    }

    /**
     * Detailed breakdown for the clickable badge modal.
     *
     * Aggregates every GR item on the same PO line (`purchase_order` +
     * `purchase_order_item`), so one PO line delivered across several GRs counts
     * once. Reversed (void) goods receipts are excluded, and the quantity pending
     * submission on the current screen is added to the invoiced side.
     *
     * @return array{ordered: int, received: int, invoiced: int, adjustment: int, status: string, label: string, color: string, reason: string, rows: array<int, array{gr_number: string, gr: int, ir: int}>}
     */
    public function breakdown(string $purchaseOrder, string $purchaseOrderItem, int $pendingQuantity = 0): array
    {
        $items = SapGoodReceiptItem::query()
            ->where('purchase_order', $purchaseOrder)
            ->where('purchase_order_item', $purchaseOrderItem)
            ->with('goodReceipt')
            ->get();

        return $this->breakdownForItems(
            $items,
            $this->reversedGoodReceiptIds($items),
            $this->billedQuantitiesFor($items->pluck('id')),
            $pendingQuantity,
        );
    }

    /**
     * The same breakdown, computed from already-loaded data so a page with many
     * lines does not re-query (and re-eager-load) per line.
     *
     * @param  Collection<int, SapGoodReceiptItem>  $items  one PO line's items (goodReceipt loaded)
     * @param  Collection<int, int>  $reversedGoodReceiptIds  `p2p_sap_good_receipt_id` values reversed in SAP
     * @param  Collection<int, int|string>  $billedByItem  billed qty keyed by mirror item id
     * @return array{ordered: int, received: int, invoiced: int, adjustment: int, status: string, label: string, color: string, reason: string, rows: array<int, array{gr_number: string, gr: int, ir: int}>}
     */
    public function breakdownForItems(
        Collection $items,
        Collection $reversedGoodReceiptIds,
        Collection $billedByItem,
        int $pendingQuantity = 0,
    ): array {
        $live = $items->reject(
            fn (SapGoodReceiptItem $item): bool => $reversedGoodReceiptIds->contains($item->p2p_sap_good_receipt_id),
        );

        $ordered = (int) ($items->max('quantity_po') ?? 0);
        $received = (int) $live->sum(
            fn (SapGoodReceiptItem $item): int => (int) ($item->quantity_served ?? $item->quantity_po),
        );
        $invoiced = (int) $live->sum(
            fn (SapGoodReceiptItem $item): int => (int) ($billedByItem[$item->id] ?? 0),
        ) + $pendingQuantity;

        $status = $this->statusFor($ordered, $received, $invoiced);

        $rows = $live
            ->map(fn (SapGoodReceiptItem $item): array => [
                'gr_number' => (string) ($item->goodReceipt?->good_receipt_number ?? '—'),
                'gr' => (int) ($item->quantity_served ?? $item->quantity_po),
                'ir' => (int) ($billedByItem[$item->id] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'ordered' => $ordered,
            'received' => $received,
            'invoiced' => $invoiced,
            'adjustment' => $pendingQuantity,
            'status' => $status->value,
            'label' => $status->getLabel(),
            'color' => $status->getColor(),
            'reason' => $status->description(),
            'rows' => $rows,
        ];
    }

    /**
     * Reconcile a whole submission (all of its GR lines) into one rollup, so the
     * list can show a 3-way badge per invoice rather than per line.
     *
     * @return array{ordered: int, received: int, invoiced: int, status: string, label: string, color: string, reason: string, lines: array<int, array{po_number: string, po_item: string, ordered: int, received: int, invoiced: int, status: string, label: string, color: string}>}
     */
    public function breakdownForInvoice(Invoice $invoice): array
    {
        $items = SapGoodReceiptItem::query()
            ->whereIn('purchase_order', $invoice->po_numbers ?? [])
            ->with('goodReceipt')
            ->get();

        $reversed = $this->reversedGoodReceiptIds($items);
        $billed = $this->billedQuantitiesFor($items->pluck('id'));

        $ordered = 0;
        $received = 0;
        $invoiced = 0;
        $lines = [];

        foreach ($items->groupBy('purchase_order') as $purchaseOrder => $poItems) {
            foreach ($poItems->groupBy('purchase_order_item') as $purchaseOrderItem => $lineItems) {
                $detail = $this->breakdownForItems($lineItems, $reversed, $billed);

                $ordered += $detail['ordered'];
                $received += $detail['received'];
                $invoiced += $detail['invoiced'];

                $lines[] = [
                    'po_number' => (string) $purchaseOrder,
                    'po_item' => (string) $purchaseOrderItem,
                    'ordered' => $detail['ordered'],
                    'received' => $detail['received'],
                    'invoiced' => $detail['invoiced'],
                    'status' => $detail['status'],
                    'label' => $detail['label'],
                    'color' => $detail['color'],
                ];
            }
        }

        $status = $this->statusFor($ordered, $received, $invoiced);

        return [
            'ordered' => $ordered,
            'received' => $received,
            'invoiced' => $invoiced,
            'status' => $status->value,
            'label' => $status->getLabel(),
            'color' => $status->getColor(),
            'reason' => $status->description(),
            'lines' => $lines,
        ];
    }

    /**
     * `p2p_sap_good_receipt_id` values reversed in SAP, for a set of items.
     *
     * @param  Collection<int, SapGoodReceiptItem>  $items
     * @return Collection<int, int>
     */
    public function reversedGoodReceiptIds(Collection $items): Collection
    {
        return GoodReceiptState::query()
            ->whereIn('p2p_sap_good_receipt_id', $items->pluck('p2p_sap_good_receipt_id')->filter()->unique())
            ->where('status', P2pStatus::Reversed->value)
            ->pluck('p2p_sap_good_receipt_id');
    }

    /**
     * Billed quantity per mirror item, in a single query.
     *
     * @param  Collection<int, int>  $itemIds
     * @return Collection<int, int|string>
     */
    public function billedQuantitiesFor(Collection $itemIds): Collection
    {
        return InvoiceLine::query()
            ->whereIn('p2p_sap_good_receipt_item_id', $itemIds)
            ->whereRelation('invoice', 'status', '!=', P2pStatus::Cancelled->value)
            ->groupBy('p2p_sap_good_receipt_item_id')
            ->selectRaw('p2p_sap_good_receipt_item_id, SUM(quantity_billed) as billed')
            ->pluck('billed', 'p2p_sap_good_receipt_item_id');
    }

    /**
     * Match a line to its mirror item, if linked.
     */
    public function matchLine(InvoiceLine $line): ?P2pThreeWayMatchStatus
    {
        $item = $line->sapItem;

        return $item === null ? null : $this->match($item, $line);
    }
}
