<?php

namespace App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages;

use App\Filament\Clusters\RfpSettings\Resources\DueTypes\DueTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDueTypes extends ListRecords
{
    protected static string $resource = DueTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
