<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Three-way match (PO vs GR vs invoice) result shown as a badge on P2P lines.
 */
enum P2pThreeWayMatchStatus: string implements HasColor, HasLabel
{
    case Matched = 'matched';
    case OpenPo = 'open_po';
    case NoMatch = 'no_match';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::OpenPo => 'Open PO',
            self::NoMatch => 'No Match',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matched => 'success',
            // Blue (info): the invoice matches what was received, but the PO
            // still has an open quantity. Not an error.
            self::OpenPo => 'info',
            self::NoMatch => 'danger',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Matched => 'PO, GR and invoice quantities agree.',
            self::OpenPo => 'The invoice matches what was received; the PO still has an open quantity.',
            self::NoMatch => 'Quantities or amounts do not reconcile (over-delivery, invoiced > received, or amount mismatch).',
        };
    }
}
