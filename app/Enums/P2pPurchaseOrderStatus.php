<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived PO status for P2P (single source of truth: P2pPurchaseOrderStatusService).
 *
 * Integer values replicate the legacy P2P `sap_purchase_orders.status_id` values.
 * "Submitted" means no longer Pending; escalated still counts as submitted.
 */
enum P2pPurchaseOrderStatus: int implements HasColor, HasLabel
{
    case Completed = 1;
    case Pending = 0;
    case PartiallySubmitted = 2;
    case FullySubmitted = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallySubmitted => 'Partially Submitted',
            self::FullySubmitted => 'Fully Submitted',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::PartiallySubmitted => 'warning',
            self::FullySubmitted => 'info',
            self::Completed => 'success',
        };
    }
}
