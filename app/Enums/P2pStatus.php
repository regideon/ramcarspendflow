<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * P2P goods-receipt / invoice workflow status.
 *
 * The integer values replicate the legacy P2P `good_receipt_statuses` IDs so flow
 * parity is preserved (see docs/p2p/06-workflow-statuses.md). Statuses 3, 5, 7 and
 * 11 are reserved/legacy and are not driven by the current flows.
 */
enum P2pStatus: int implements HasColor, HasLabel
{
    case Pending = 1;
    case SubmittedToAp = 2;
    case ApReview = 3;
    case SubmittedToChecker = 4;
    case CheckerReview = 5;
    case Completed = 6;
    case RejectedLegacy = 7;
    case EscalatedByVendor = 8;
    case EscalatedByProcessor = 9;
    case EscalateToProcurement = 10;
    case Approved = 11;
    case Rejected = 12;
    case InvoiceParked = 13;
    case InvoicePosted = 14;
    case ReturnedToVendor = 15;
    case ReturnedToApProcessor = 16;
    case Cancelled = 17;
    case EscalatedToWarehouse = 18;
    case PartiallyEscalated = 19;
    case Reversed = 20;
    case PendingPost = 21;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::SubmittedToAp => 'Submitted to AP',
            self::ApReview => 'AP Review',
            self::SubmittedToChecker => 'Submitted to Checker',
            self::CheckerReview => 'Checker Review',
            self::Completed => 'Completed',
            self::RejectedLegacy => 'Rejected (legacy)',
            self::EscalatedByVendor => 'Escalated by Vendor',
            self::EscalatedByProcessor => 'Escalated by Processor',
            self::EscalateToProcurement => 'Escalate to Procurement',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::InvoiceParked => 'Invoice Parked',
            self::InvoicePosted => 'Invoice Posted',
            self::ReturnedToVendor => 'Returned to Vendor',
            self::ReturnedToApProcessor => 'Returned to AP Processor',
            self::Cancelled => 'Cancelled',
            self::EscalatedToWarehouse => 'Escalated to Warehouse',
            self::PartiallyEscalated => 'Partially Escalated',
            self::Reversed => 'Reversed',
            self::PendingPost => 'Pending Post',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Rejected, self::RejectedLegacy, self::Cancelled => 'danger',
            self::EscalatedByVendor, self::EscalatedByProcessor,
            self::EscalateToProcurement, self::EscalatedToWarehouse,
            self::PartiallyEscalated => 'danger',
            self::ReturnedToVendor, self::ReturnedToApProcessor => 'warning',
            self::InvoiceParked, self::InvoicePosted, self::PendingPost => 'info',
            default => 'gray',
        };
    }

    /**
     * Statuses not driven by the current flows (legacy/reserved).
     */
    public function isReserved(): bool
    {
        return in_array($this, [self::ApReview, self::CheckerReview, self::RejectedLegacy, self::Approved], true);
    }

    /**
     * Whether a vendor may still edit a record at this status.
     */
    public function isEditableByVendor(): bool
    {
        return in_array($this, [self::Pending, self::ReturnedToVendor], true);
    }

    /**
     * Escalation hop statuses (Procurement / Warehouse).
     */
    public function isEscalated(): bool
    {
        return in_array($this, [
            self::EscalatedByVendor,
            self::EscalatedByProcessor,
            self::EscalateToProcurement,
            self::EscalatedToWarehouse,
            self::PartiallyEscalated,
        ], true);
    }
}
