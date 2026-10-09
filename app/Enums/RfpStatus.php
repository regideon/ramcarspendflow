<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * RFP lifecycle. Proposed list; see docs/03-rfp-approval-flow.md §6 (open question R8).
 */
enum RfpStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case ForApReview = 'for_ap_review';
    case ForApproval = 'for_approval';
    case ForApProcessing = 'for_ap_processing';
    case ForRevision = 'for_revision';
    case ForAccountingApproval = 'for_accounting_approval'; // Step 5: AP Supervisor, then AP Manager
    case ForTreasury = 'for_treasury';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ForApReview => 'For AP Review',
            self::ForApproval => 'For Approval',
            self::ForApProcessing => 'For AP Processing',
            self::ForRevision => 'For Revision',
            self::ForAccountingApproval => 'For Accounting Approval',
            self::ForTreasury => 'For Treasury',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::ForRevision => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            default => 'info',
        };
    }

    /**
     * Whether the requestor may still edit the RFP.
     */
    public function isEditableByRequestor(): bool
    {
        return in_array($this, [self::Draft, self::ForRevision], true);
    }
}
