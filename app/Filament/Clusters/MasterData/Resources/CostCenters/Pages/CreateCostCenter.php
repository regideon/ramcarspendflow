<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters\Pages;

use App\Filament\Clusters\MasterData\Resources\CostCenters\CostCenterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCostCenter extends CreateRecord
{
    protected static string $resource = CostCenterResource::class;
}
