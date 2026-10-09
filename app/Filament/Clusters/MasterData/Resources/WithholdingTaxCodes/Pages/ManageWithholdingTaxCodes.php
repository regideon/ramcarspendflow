<?php

namespace App\Filament\Clusters\MasterData\Resources\WithholdingTaxCodes\Pages;

use App\Filament\Clusters\MasterData\Resources\WithholdingTaxCodes\WithholdingTaxCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWithholdingTaxCodes extends ManageRecords
{
    protected static string $resource = WithholdingTaxCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
