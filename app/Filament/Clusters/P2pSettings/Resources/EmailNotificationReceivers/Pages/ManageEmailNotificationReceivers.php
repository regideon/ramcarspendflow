<?php

namespace App\Filament\Clusters\P2pSettings\Resources\EmailNotificationReceivers\Pages;

use App\Filament\Clusters\P2pSettings\Resources\EmailNotificationReceivers\EmailNotificationReceiverResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailNotificationReceivers extends ManageRecords
{
    protected static string $resource = EmailNotificationReceiverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
