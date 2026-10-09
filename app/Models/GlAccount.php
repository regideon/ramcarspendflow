<?php

namespace App\Models;

use Database\Factories\GlAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * SAP G/L account (SAKNR).
 */
#[Fillable(['code', 'description', 'is_active'])]
class GlAccount extends Model
{
    /** @use HasFactory<GlAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<CostCenter, $this>
     */
    public function costCenters(): BelongsToMany
    {
        return $this->belongsToMany(CostCenter::class)
            ->withPivot(['expense_group', 'category', 'account_type', 'is_active'])
            ->withTimestamps();
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
