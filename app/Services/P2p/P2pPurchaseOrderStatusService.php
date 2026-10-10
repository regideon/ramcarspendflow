<?php

namespace App\Services\P2p;

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapPurchaseOrder;
use Illuminate\Support\Collection;

/**
 * Derives and persists a PO's status from its GR states. Single source of truth:
 * nothing else writes `p2p_sap_purchase_orders.status_id`.
 *
 * Rules (legacy parity, docs/p2p/06-workflow-statuses.md):
 * - all GRs pending            → Pending
 * - all GRs submitted, not all parked → Fully Submitted
 * - all GRs parked/posted/completed   → Completed
 * - otherwise (some pending, or any rejected/returned) → Partially Submitted
 */
class P2pPurchaseOrderStatusService
{
    /**
     * Statuses that count as "still pending" at GR level.
     */
    private const array NOT_SUBMITTED = [P2pStatus::Pending->value];

    /**
     * Statuses that drop a PO back to Partially Submitted.
     */
    private const array REJECTED_OR_RETURNED = [
        P2pStatus::Rejected->value,
        P2pStatus::RejectedLegacy->value,
        P2pStatus::ReturnedToVendor->value,
    ];

    /**
     * GR statuses that mean the invoice has been parked/posted/completed in SAP.
     */
    private const array PARKED = [
        P2pStatus::InvoiceParked->value,
        P2pStatus::InvoicePosted->value,
        P2pStatus::Completed->value,
    ];

    /**
     * Recompute and persist the PO status. Returns the derived status.
     */
    public function recalculate(SapPurchaseOrder $purchaseOrder): P2pPurchaseOrderStatus
    {
        $status = $this->derive($purchaseOrder);

        if ($purchaseOrder->status_id !== $status) {
            $purchaseOrder->forceFill(['status_id' => $status])->save();
        }

        return $status;
    }

    public function derive(SapPurchaseOrder $purchaseOrder): P2pPurchaseOrderStatus
    {
        $states = GoodReceiptState::query()
            ->where('purchase_order', $purchaseOrder->purchase_order)
            ->get();

        return $this->statusFor($states);
    }

    /**
     * @param  Collection<int, GoodReceiptState>  $states
     */
    public function statusFor(Collection $states): P2pPurchaseOrderStatus
    {
        if ($states->isEmpty()) {
            return P2pPurchaseOrderStatus::Pending;
        }

        $statuses = $states->map(fn (GoodReceiptState $state): int => $state->status->value);

        if ($statuses->every(fn (int $status): bool => in_array($status, self::NOT_SUBMITTED, true))) {
            return P2pPurchaseOrderStatus::Pending;
        }

        if ($statuses->every(fn (int $status): bool => in_array($status, self::PARKED, true))) {
            return P2pPurchaseOrderStatus::Completed;
        }

        $allSubmitted = $statuses->every(fn (int $status): bool => ! in_array($status, self::NOT_SUBMITTED, true)
            && ! in_array($status, self::REJECTED_OR_RETURNED, true));

        return $allSubmitted ? P2pPurchaseOrderStatus::FullySubmitted : P2pPurchaseOrderStatus::PartiallySubmitted;
    }
}
