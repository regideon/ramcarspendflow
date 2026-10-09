<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SAP vendor master (LIFNR). Shared by RFP (payee) and P2P (supplier portal).
 */
#[Fillable(['code', 'name', 'email', 'country', 'tax_number', 'currency', 'account_group_id', 'is_active', 'last_po_synced_at'])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AccountGroup, $this>
     */
    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    /**
     * Company codes the vendor is extended to, with the per-company SAP data.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['reconciliation_account', 'payment_terms', 'payment_methods', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Portal logins for this vendor.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_po_synced_at' => 'datetime',
        ];
    }
}
