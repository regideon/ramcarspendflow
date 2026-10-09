<?php

namespace App\Models;

use Database\Factories\AccountGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SAP vendor account group (KTOKK).
 */
#[Fillable(['code', 'description', 'is_active'])]
class AccountGroup extends Model
{
    /** @use HasFactory<AccountGroupFactory> */
    use HasFactory;

    /**
     * @return HasMany<Vendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
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
