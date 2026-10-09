<?php

namespace App\Filament\Clusters\MasterData\Resources\WithholdingTaxTypes\Pages;

use App\Filament\Clusters\MasterData\Resources\WithholdingTaxTypes\WithholdingTaxTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWithholdingTaxTypes extends ManageRecords
{
    protected static string $resource = WithholdingTaxTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
