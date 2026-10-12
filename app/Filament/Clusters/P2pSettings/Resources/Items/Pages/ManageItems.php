<?php

namespace App\Filament\Clusters\P2pSettings\Resources\Items\Pages;

use App\Filament\Clusters\P2pSettings\Resources\Items\ItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItems extends ManageRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
