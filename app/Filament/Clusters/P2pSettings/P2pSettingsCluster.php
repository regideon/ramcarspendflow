<?php

namespace App\Filament\Clusters\P2pSettings;

use App\Filament\Concerns\RestrictedToAdmins;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * P2P-only setup: material/SLOC classification, items, delivery types, escalation
 * routes and points of contact (docs/p2p/03-master-data.md).
 */
class P2pSettingsCluster extends Cluster
{
    use RestrictedToAdmins;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'P2P Settings';

    protected static ?int $navigationSort = 3;
}
