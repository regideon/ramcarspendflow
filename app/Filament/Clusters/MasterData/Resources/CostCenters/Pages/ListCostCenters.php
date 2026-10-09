<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters\Pages;

use App\Filament\Clusters\MasterData\Resources\CostCenters\CostCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCostCenters extends ListRecords
{
    protected static string $resource = CostCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
