<?php

namespace App\Filament\Clusters\P2pSettings\Resources\SlocClassifications\Pages;

use App\Filament\Clusters\P2pSettings\Resources\SlocClassifications\SlocClassificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlocClassifications extends ManageRecords
{
    protected static string $resource = SlocClassificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
