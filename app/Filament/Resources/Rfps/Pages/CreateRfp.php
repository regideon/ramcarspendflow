<?php

namespace App\Filament\Resources\Rfps\Pages;

use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Rfp\Rfp;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateRfp extends CreateRecord
{
    use HasCommentsChat;

    protected static string $resource = RfpResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RfpProgress::class,
        ];
    }

    protected static ?string $title = 'New request for payment';

    public bool $submitAfterCreate = false;

    public bool $submitFailed = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requestor_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Rfp $rfp */
        $rfp = $this->getRecord();
        $rfp->refreshTotals();

        if (! $this->submitAfterCreate) {
            return;
        }

        try {
            $rfp->submit(auth()->user());
        } catch (ValidationException $exception) {
            $this->submitFailed = true;

            Notification::make()
                ->title('Saved as draft, but not submitted')
                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Save draft')
                ->color('gray'),
            $this->createAndSubmitAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function createAndSubmitAction(): Action
    {
        return Action::make('createAndSubmit')
            ->label('Save & submit')
            ->action(function (): void {
                $this->submitAfterCreate = true;
                $this->create();
            })
            ->keyBindings(['mod+shift+s']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->submitFailed) {
            return null;
        }

        return $this->submitAfterCreate ? 'Request submitted to AP' : 'Draft saved';
    }

    protected function getRedirectUrl(): string
    {
        return $this->submitFailed
            ? RfpResource::getUrl('edit', ['record' => $this->getRecord()])
            : RfpResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
