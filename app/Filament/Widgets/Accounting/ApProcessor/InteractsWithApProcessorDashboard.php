<?php

namespace App\Filament\Widgets\Accounting\ApProcessor;

use App\Enums\RoleName;
use App\Services\Accounting\ApProcessorDashboard;

/**
 * Shared by the AP Processor dashboard widgets: role gate and data source.
 */
trait InteractsWithApProcessorDashboard
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole(RoleName::ApProcessor) ?? false;
    }

    protected function dashboard(): ApProcessorDashboard
    {
        return new ApProcessorDashboard(auth()->user());
    }
}
