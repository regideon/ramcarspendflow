<?php

namespace App\Models;

use App\Enums\TaxCodeCategory;
use Database\Factories\TaxCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SAP input VAT code (MWSKZ).
 */
#[Fillable(['code', 'description', 'category', 'rate', 'tax_procedure', 'is_active'])]
class TaxCode extends Model
{
    /** @use HasFactory<TaxCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TaxCodeCategory::class,
            'rate' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
