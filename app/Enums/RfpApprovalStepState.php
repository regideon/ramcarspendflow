<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where one step of an RFP's approval route stands.
 */
enum RfpApprovalStepState: string implements HasLabel
{
    case Pending = 'pending';
    case Active = 'active'; // waiting for its role now
    case Approved = 'approved';
    case Returned = 'returned'; // sent back to the requestor or an earlier stage
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped'; // e.g. the matrix on a pre-approved RFP
    case Superseded = 'superseded'; // left open when the route was returned, rejected or cancelled

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Waiting',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Skipped => 'Skipped',
            self::Superseded => 'Superseded',
        };
    }

    /**
     * States still to be acted on.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Active];
    }
}
