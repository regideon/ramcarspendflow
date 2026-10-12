<?php

namespace App\Filament\Widgets\Rfp\Requestor;

use App\Enums\RoleName;
use App\Services\Rfp\RfpRequestorDashboard;

/**
 * Shared by the Requestor dashboard widgets: role gate and data source.
 */
trait InteractsWithRequestorDashboard
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole(RoleName::Requestor) ?? false;
    }

    protected function dashboard(): RfpRequestorDashboard
    {
        return new RfpRequestorDashboard(auth()->user());
    }
}
