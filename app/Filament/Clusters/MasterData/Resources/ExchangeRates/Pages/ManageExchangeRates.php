<?php

namespace App\Filament\Clusters\MasterData\Resources\ExchangeRates\Pages;

use App\Filament\Clusters\MasterData\Resources\ExchangeRates\ExchangeRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRates extends ManageRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
