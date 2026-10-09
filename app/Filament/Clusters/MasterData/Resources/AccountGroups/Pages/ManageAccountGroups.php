<?php

namespace App\Filament\Clusters\MasterData\Resources\AccountGroups\Pages;

use App\Filament\Clusters\MasterData\Resources\AccountGroups\AccountGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountGroups extends ManageRecords
{
    protected static string $resource = AccountGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
