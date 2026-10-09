<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SAP company code (BUKRS). Shared by RFP and P2P.
 */
#[Fillable(['code', 'name', 'short_name', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<CostCenter, $this>
     */
    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    /**
     * @return BelongsToMany<Vendor, $this>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class)
            ->withPivot(['reconciliation_account', 'payment_terms', 'payment_methods', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Users who may transact or approve for this company.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
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
