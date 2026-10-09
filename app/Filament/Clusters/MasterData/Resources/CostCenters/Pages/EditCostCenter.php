<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters\Pages;

use App\Filament\Clusters\MasterData\Resources\CostCenters\CostCenterResource;
use Filament\Resources\Pages\EditRecord;

class EditCostCenter extends EditRecord
{
    protected static string $resource = CostCenterResource::class;
}
