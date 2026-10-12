<?php

namespace App\Filament\Widgets\P2p\Vendor;

use App\Enums\RoleName;
use App\Services\P2p\P2pVendorDashboard;

/**
 * Shared by the Vendor dashboard widgets: role gate and data source.
 */
trait InteractsWithVendorDashboard
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ?? false;
    }

    protected function dashboard(): P2pVendorDashboard
    {
        return new P2pVendorDashboard(auth()->user());
    }
}
