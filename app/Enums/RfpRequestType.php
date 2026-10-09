<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RfpRequestType: string implements HasLabel
{
    case Standard = 'standard';
    case Reimbursement = 'reimbursement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Reimbursement => 'Reimbursement',
        };
    }
}
