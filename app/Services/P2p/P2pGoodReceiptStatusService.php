<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Models\P2p\Escalation;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceiptItem;

/**
 * Derives a GR's state from its invoice lines and escalations. This is the single
 * writer of `p2p_good_receipt_states.status` — nothing else sets it by hand, so the
 * two status machines the legacy system kept in step manually cannot drift.
 *
 * See docs/p2p/02-data-model.md §2.5 and 06-workflow-statuses.md §4.
 */
class P2pGoodReceiptStatusService
{
    /**
     * Recompute and persist the state for a GR. Returns the derived status.
     */
    public function recalculate(GoodReceiptState $state): P2pStatus
    {
        $status = $this->derive($state);

        if ($state->status !== $status) {
            $state->forceFill(['status' => $status, 'is_fully_processed' => $status === P2pStatus::Completed])->save();
        }

        return $status;
    }

    /**
     * Status a GR should have right now (does not persist).
     */
    public function derive(GoodReceiptState $state): P2pStatus
    {
        // A GR reversed in SAP is void regardless of anything else.
        if ($state->reversed_at !== null) {
            return P2pStatus::Reversed;
        }

        // An open escalation owns the GR's status until it is resolved.
        if ($escalation = $this->openEscalation($state)) {
            return $this->escalationHopStatus($escalation) ?? $escalation->from_status ?? P2pStatus::Pending;
        }

        // Otherwise the GR follows the worth-most-recent invoice that bills it.
        return $this->latestInvoice($state)?->status ?? P2pStatus::Pending;
    }

    /**
     * Recalculate the state(s) for a GR after an invoice change.
     */
    public function recalculateForInvoice(Invoice $invoice): void
    {
        $grIds = InvoiceLine::query()
            ->where('p2p_invoice_id', $invoice->id)
            ->join('p2p_sap_good_receipt_items', 'p2p_sap_good_receipt_items.id', '=', 'p2p_invoice_lines.p2p_sap_good_receipt_item_id')
            ->distinct()
            ->pluck('p2p_sap_good_receipt_items.p2p_sap_good_receipt_id');

        GoodReceiptState::query()
            ->whereIn('p2p_sap_good_receipt_id', $grIds)
            ->get()
            ->each(fn (GoodReceiptState $state) => $this->recalculate($state));
    }

    protected function openEscalation(GoodReceiptState $state): ?Escalation
    {
        return Escalation::query()
            ->where('p2p_good_receipt_state_id', $state->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    /**
     * The P2pStatus configured for the escalation's current hop, if any.
     */
    protected function escalationHopStatus(Escalation $escalation): ?P2pStatus
    {
        $value = $escalation->reason
            ?->steps()
            ->where('step_order', $escalation->current_step_order)
            ->value('status_id');

        return match (true) {
            $value instanceof P2pStatus => $value,
            $value === null => null,
            default => P2pStatus::from((int) $value),
        };
    }

    /**
     * The most recent invoice that bills any line of this GR.
     */
    protected function latestInvoice(GoodReceiptState $state): ?Invoice
    {
        $sapItemIds = SapGoodReceiptItem::query()
            ->where('p2p_sap_good_receipt_id', $state->p2p_sap_good_receipt_id)
            ->pluck('id');

        if ($sapItemIds->isEmpty()) {
            return null;
        }

        $invoiceId = InvoiceLine::query()
            ->whereIn('p2p_sap_good_receipt_item_id', $sapItemIds)
            ->latest('p2p_invoice_id')
            ->value('p2p_invoice_id');

        return $invoiceId ? Invoice::find($invoiceId) : null;
    }
}
