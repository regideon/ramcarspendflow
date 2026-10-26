<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'company_id', 'vendor_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Memoised vendor assignments for the current request. Filament evaluates
     * the resource query several times per page (table, tabs, badges), and each
     * `vendorIds()` call used to re-query the pivot, producing an N+1.
     *
     * @var array<int, int>|null
     */
    protected ?array $memoisedVendorIds = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $memoisedVendorCodes = null;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * Home company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Companies the user may transact or approve for.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    /**
     * Set only for Vendor (supplier) logins.
     *
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The SAP vendor codes this login may transact for. A supplier may be
     * registered under several codes (e.g. a local supplier and a service
     * provider record for the same legal entity).
     *
     * @return BelongsToMany<Vendor, $this>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_user')->withTimestamps();
    }

    /**
     * Vendor ids for scoping, preferring the pivot and falling back to the
     * legacy `users.vendor_id` for rows that predate the pivot.
     *
     * @return array<int, int>
     */
    public function vendorIds(): array
    {
        if ($this->memoisedVendorIds !== null) {
            return $this->memoisedVendorIds;
        }

        $ids = $this->relationLoaded('vendors')
            ? $this->vendors->modelKeys()
            : $this->vendors()->pluck('vendors.id')->all();

        if ($ids === [] && $this->vendor_id !== null) {
            return $this->memoisedVendorIds = [(int) $this->vendor_id];
        }

        return $this->memoisedVendorIds = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Vendor codes (the SAP LIFNR values) for the PO/GR `vendor_code` columns.
     *
     * @return array<int, string>
     */
    public function vendorCodes(): array
    {
        if ($this->memoisedVendorCodes !== null) {
            return $this->memoisedVendorCodes;
        }

        $codes = $this->relationLoaded('vendors')
            ? $this->vendors->pluck('code')
            : $this->vendors()->pluck('vendors.code');

        if ($codes->isEmpty() && $this->vendor_id !== null) {
            $code = Vendor::query()->whereKey($this->vendor_id)->value('code');

            return $this->memoisedVendorCodes = filled($code) ? [$code] : [];
        }

        return $this->memoisedVendorCodes = $codes->filter()->unique()->values()->all();
    }

    /**
     * Keep the legacy single `vendor_id` in the pivot when rows are created or
     * updated outside the Filament form (factories, seeders, tests).
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->syncLegacyVendor();
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('vendor_id')) {
                $user->syncLegacyVendor();
            }
        });
    }

    public function syncLegacyVendor(): void
    {
        if ($this->vendor_id === null) {
            return;
        }

        if (! Vendor::query()->whereKey($this->vendor_id)->exists()) {
            return;
        }

        $this->vendors()->syncWithoutDetaching([$this->vendor_id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
