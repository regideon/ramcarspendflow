<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Furthest stage a purchase order's down payment has reached, derived from the
 * SAP EKBE (PO history) `VGABE` events: A = request, 4 = payment, C/V = clearing.
 */
enum P2pDownPaymentStatus: string implements HasLabel
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Cleared = 'cleared';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Down payment requested',
            self::Paid => 'Down payment paid',
            self::Cleared => 'Down payment cleared',
        };
    }
}
