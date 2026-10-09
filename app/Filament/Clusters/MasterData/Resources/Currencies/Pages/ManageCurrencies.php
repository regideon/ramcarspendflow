<?php

namespace App\Filament\Clusters\MasterData\Resources\Currencies\Pages;

use App\Filament\Clusters\MasterData\Resources\Currencies\CurrencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCurrencies extends ManageRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
