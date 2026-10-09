<?php

namespace App\Filament\Concerns;

use App\Enums\RoleName;

/**
 * Limits a Filament resource, page or cluster to users with the Admin role.
 */
trait RestrictedToAdmins
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(RoleName::Admin) ?? false;
    }
}
