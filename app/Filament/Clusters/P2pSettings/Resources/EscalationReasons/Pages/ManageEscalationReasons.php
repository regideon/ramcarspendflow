<?php

namespace App\Filament\Clusters\P2pSettings\Resources\EscalationReasons\Pages;

use App\Filament\Clusters\P2pSettings\Resources\EscalationReasons\EscalationReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEscalationReasons extends ManageRecords
{
    protected static string $resource = EscalationReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
