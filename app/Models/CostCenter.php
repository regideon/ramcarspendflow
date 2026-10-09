<?php

namespace App\Models;

use Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * SAP cost center (KOSTL), per company.
 */
#[Fillable(['company_id', 'code', 'description', 'is_active'])]
class CostCenter extends Model
{
    /** @use HasFactory<CostCenterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * G/L accounts that may be charged to this cost center.
     *
     * @return BelongsToMany<GlAccount, $this>
     */
    public function glAccounts(): BelongsToMany
    {
        return $this->belongsToMany(GlAccount::class)
            ->withPivot(['expense_group', 'category', 'account_type', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Whether the cost center is Manufacturing (MFG): 3rd digit of the code is 3 or above.
     * OPEX is 1 or 2. Null when the code carries no expense type (e.g. 0).
     */
    public function isManufacturing(): ?bool
    {
        $digit = (int) substr((string) $this->code, 2, 1);

        return $digit === 0 ? null : $digit >= 3;
    }

    /**
     * Expense-type rule: MFG cost centers take G/L accounts starting with 5,
     * OPEX ones G/L accounts starting with 6. Other G/L accounts are not restricted.
     */
    public function allowsGlCode(string $glCode): bool
    {
        return match ($glCode[0] ?? null) {
            '5' => $this->isManufacturing() !== false,
            '6' => $this->isManufacturing() !== true,
            default => true,
        };
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
