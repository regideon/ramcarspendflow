<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Drives the VAT summary buckets (vatable / zero-rated / exempt).
 */
enum TaxCodeCategory: string implements HasLabel
{
    case Taxable = 'taxable';
    case ZeroRated = 'zero_rated';
    case VatExempt = 'vat_exempt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Taxable => 'Taxable',
            self::ZeroRated => 'Zero Rated',
            self::VatExempt => 'VAT Exempt',
        };
    }
}
