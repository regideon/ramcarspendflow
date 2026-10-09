<?php

namespace App\Filament\Clusters\MasterData\Resources\TaxCodes\Pages;

use App\Filament\Clusters\MasterData\Resources\TaxCodes\TaxCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaxCodes extends ManageRecords
{
    protected static string $resource = TaxCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
