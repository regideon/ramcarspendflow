<?php

namespace App\Models;

use Database\Factories\WithholdingTaxCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SAP withholding tax code (WT_WITHCD) with its BIR ATC and rate.
 */
#[Fillable(['withholding_tax_type_id', 'code', 'atc', 'description', 'rate', 'is_active'])]
class WithholdingTaxCode extends Model
{
    /** @use HasFactory<WithholdingTaxCodeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WithholdingTaxType, $this>
     */
    public function withholdingTaxType(): BelongsTo
    {
        return $this->belongsTo(WithholdingTaxType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
