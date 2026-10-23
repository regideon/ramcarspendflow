<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\CompanyRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approval role a user holds in one company. The same person may hold a role in
 * several companies, and different roles in different companies.
 */
#[Table('company_role_user')]
#[Fillable(['company_id', 'user_id', 'role'])]
class CompanyRole extends Model
{
    /** @use HasFactory<CompanyRoleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => RoleName::class,
        ];
    }
}
