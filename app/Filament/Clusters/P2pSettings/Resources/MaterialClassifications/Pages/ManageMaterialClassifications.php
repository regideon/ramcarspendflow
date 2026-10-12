<?php

namespace App\Filament\Clusters\P2pSettings\Resources\MaterialClassifications\Pages;

use App\Filament\Clusters\P2pSettings\Resources\MaterialClassifications\MaterialClassificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMaterialClassifications extends ManageRecords
{
    protected static string $resource = MaterialClassificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
