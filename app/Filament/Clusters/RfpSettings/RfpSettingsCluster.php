<?php

namespace App\Filament\Clusters\RfpSettings;

use App\Filament\Concerns\RestrictedToAdmins;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * RFP-only setup: expense categories and the approval matrix.
 */
class RfpSettingsCluster extends Cluster
{
    use RestrictedToAdmins;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'RFP Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;
}
