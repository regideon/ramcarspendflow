<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who the requestor paid, on a Reimbursement line.
 */
enum RfpVendorType: string implements HasLabel
{
    case Vendor = 'vendor';
    case OneTimeVendor = 'one_time_vendor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vendor => 'Existing vendor',
            self::OneTimeVendor => 'One-time vendor',
        };
    }
}
