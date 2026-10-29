<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
     * Approval roles the user holds, per company.
     *
     * @return HasMany<CompanyRole, $this>
     */
    public function companyRoles(): HasMany
    {
        return $this->hasMany(CompanyRole::class);
    }

    /**
     * The approval roles the user holds in a company.
     *
     * @return list<string>
     */
    public function rolesIn(?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }

        // From the loaded assignments: pages ask once per action, so this queries once.
        return $this->companyRoles
            ->filter(fn (CompanyRole $assignment): bool => (int) $assignment->company_id === $companyId)
            ->map(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->values()
            ->all();
    }

    /**
     * Replace the user's approval roles per company, and keep their Spatie roles in step:
     * they hold an approval role's screens when they hold that role in any company.
     *
     * @param  list<array{role: RoleName|string, companies: list<int|string>}>  $rows
     */
    public function syncCompanyRoles(array $rows): void
    {
        $assignments = collect($rows)
            ->flatMap(fn (array $row): array => array_map(fn (int|string $companyId): array => [
                'company_id' => (int) $companyId,
                'role' => $row['role'] instanceof RoleName ? $row['role']->value : $row['role'],
            ], $row['companies'] ?? []))
            ->unique(fn (array $assignment): string => $assignment['company_id'].'|'.$assignment['role'])
            ->values();

        DB::transaction(function () use ($assignments): void {
            $this->companyRoles()->delete();
            $this->companyRoles()->createMany($assignments->all());

            $companyScoped = array_map(fn (RoleName $role): string => $role->value, RoleName::companyScoped());

            $this->syncRoles([
                ...$this->getRoleNames()->diff($companyScoped)->all(),
                ...$assignments->pluck('role')->unique()->all(),
            ]);
        });

        $this->unsetRelation('companyRoles');
    }

    /**
     * The user's approval roles as form rows: one per role, with its companies.
     *
     * @return list<array{role: string, companies: list<int>}>
     */
    public function companyRoleRows(): array
    {
        return $this->companyRoles()->orderBy('role')->get()
            ->groupBy(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->map(fn ($assignments, string $role): array => ['role' => $role, 'companies' => $assignments->pluck('company_id')->all()])
            ->values()
            ->all();
    }

    /**
     * Whether the user holds a matrix approver role in any company.
     */
    public function isMatrixApprover(): bool
    {
        return $this->companyRoles->contains(
            fn (CompanyRole $assignment): bool => in_array($assignment->role, RoleName::rfpApprovers(), true),
        );
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
