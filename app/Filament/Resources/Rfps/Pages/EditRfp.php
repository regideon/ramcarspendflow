<?php

namespace App\Filament\Resources\Rfps\Pages;

use App\Enums\RfpStatus;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Rfp\Rfp;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRfp extends EditRecord
{
    use HasCommentsChat;

    protected static string $resource = RfpResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RfpProgress::class,
        ];
    }

    public function getTitle(): string
    {
        return "Edit {$this->getRecord()->rfp_number}";
    }

    protected function afterSave(): void
    {
        $this->getRecord()->refreshTotals();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->label('Delete draft'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save draft')
                ->color('gray'),
            $this->saveAndSubmitAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndSubmitAction(): Action
    {
        return Action::make('saveAndSubmit')
            ->label('Save & submit')
            ->visible(fn (): bool => $this->getRecord()->status === RfpStatus::Draft)
            ->action(function (): void {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                /** @var Rfp $rfp */
                $rfp = $this->getRecord();

                try {
                    $rfp->submit(auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Saved as draft, but not submitted')
                        ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title("{$rfp->rfp_number} submitted to AP")->success()->send();

                $this->redirect(RfpResource::getUrl('view', ['record' => $rfp]));
            })
            ->keyBindings(['mod+shift+s']);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft saved';
    }
}
