<?php

namespace App\Filament\Clusters\MasterData;

use App\Filament\Concerns\RestrictedToAdmins;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * SAP-backed master data shared by the RFP and P2P modules.
 */
class MasterDataCluster extends Cluster
{
    use RestrictedToAdmins;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;
}
