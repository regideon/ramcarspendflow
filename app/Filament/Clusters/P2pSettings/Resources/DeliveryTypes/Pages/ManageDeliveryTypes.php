<?php

namespace App\Filament\Clusters\P2pSettings\Resources\DeliveryTypes\Pages;

use App\Filament\Clusters\P2pSettings\Resources\DeliveryTypes\DeliveryTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryTypes extends ManageRecords
{
    protected static string $resource = DeliveryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
