<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Material bucket a P2P line belongs to, derived from its storage location (SLOC)
 * classification. Drives the Warehouse escalation hop (Raw Materials / Spare Parts).
 */
enum P2pMaterialType: string implements HasColor, HasLabel
{
    case RawMaterial = 'raw_material';
    case SparePart = 'spare_part';
    case Unclassified = 'unclassified';

    public function getLabel(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw Materials',
            self::SparePart => 'Spare Parts',
            self::Unclassified => 'Unclassified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RawMaterial => 'info',
            self::SparePart => 'warning',
            self::Unclassified => 'gray',
        };
    }
}
