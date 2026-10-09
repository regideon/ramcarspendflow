<?php

namespace App\Filament\Clusters\MasterData\Resources\GlAccounts\Pages;

use App\Filament\Clusters\MasterData\Resources\GlAccounts\GlAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGlAccounts extends ManageRecords
{
    protected static string $resource = GlAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
