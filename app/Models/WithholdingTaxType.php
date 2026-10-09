<?php

namespace App\Models;

use Database\Factories\WithholdingTaxTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SAP withholding tax type (WITHT).
 */
#[Fillable(['code', 'name', 'is_active'])]
class WithholdingTaxType extends Model
{
    /** @use HasFactory<WithholdingTaxTypeFactory> */
    use HasFactory;

    /**
     * @return HasMany<WithholdingTaxCode, $this>
     */
    public function codes(): HasMany
    {
        return $this->hasMany(WithholdingTaxCode::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
