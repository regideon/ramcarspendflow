<?php

namespace App\Filament\Resources\Rfps\Pages;

use App\Filament\Resources\Rfps\Actions\SubmitRfpAction;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRfp extends ViewRecord
{
    protected static string $resource = RfpResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RfpProgress::class,
        ];
    }

    public function getTitle(): string
    {
        return $this->getRecord()->rfp_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => RfpResource::canEdit($this->getRecord())),
            SubmitRfpAction::make(),
        ];
    }
}
