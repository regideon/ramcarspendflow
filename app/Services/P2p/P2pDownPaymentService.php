<?php

namespace App\Services\P2p;

use App\Enums\P2pDownPaymentStatus;
use App\Models\P2p\SapPurchaseOrder;

/**
 * Read-only down-payment view for a PO. The data comes from the SAP mirror
 * (EKPO expected / EKBE actual), so this only derives what the UI needs.
 */
class P2pDownPaymentService
{
    public function stateFor(SapPurchaseOrder $purchaseOrder): ?P2pDownPaymentStatus
    {
        return $purchaseOrder->down_payment_status;
    }

    /**
     * Remaining PO balance after the down payment (e.g. the 90% invoiced via P2P).
     */
    public function remainingBalance(SapPurchaseOrder $purchaseOrder): ?string
    {
        if (! $purchaseOrder->has_down_payment || $purchaseOrder->down_payment_amount === null || $purchaseOrder->total_amount === null) {
            return null;
        }

        return bcsub((string) $purchaseOrder->total_amount, (string) $purchaseOrder->down_payment_amount, 2);
    }
}
