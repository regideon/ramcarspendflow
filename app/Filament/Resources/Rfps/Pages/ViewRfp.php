<?php

namespace App\Filament\Resources\Rfps\Pages;

use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\Rfps\Actions\SubmitRfpAction;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Rfp\Rfp;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewRfp extends ViewRecord
{
    use HasCommentsChat;

    protected static string $resource = RfpResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(Rfp::VIEW_RELATIONS);
    }

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
