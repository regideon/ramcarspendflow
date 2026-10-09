<?php

namespace App\Filament\Resources\Rfps\Actions;

use App\Enums\RfpStatus;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Models\Rfp\Rfp;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Submits a draft RFP to AP Processor review (Step 2).
 */
class SubmitRfpAction
{
    public static function make(): Action
    {
        return Action::make('submit')
            ->label('Submit')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (Rfp $record): bool => $record->status === RfpStatus::Draft && $record->requestor_id === auth()->id())
            ->requiresConfirmation()
            ->modalHeading(fn (Rfp $record): string => "Submit {$record->rfp_number}?")
            ->modalDescription(function (Rfp $record): HtmlString {
                $record->refreshTotals();

                return new HtmlString(sprintf(
                    'Total <strong>PHP %s</strong>, due %s (%s).<br>Route: %s',
                    number_format((float) $record->total_php_amount, 2),
                    e($record->dueType?->dueDateFrom()->format('M j, Y') ?? '—'),
                    e($record->dueType?->name ?? 'no due type'),
                    e(RfpForm::approvalRoute($record->request_type, $record->total_php_amount, $record->is_direct_to_vp, $record->is_pre_approved)),
                ));
            })
            ->modalSubmitActionLabel('Submit')
            ->action(function (Rfp $record, Action $action): void {
                try {
                    $record->submit(auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Cannot submit yet')
                        ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->title("{$record->rfp_number} submitted to AP")
                    ->success()
                    ->send();

                $action->redirect(RfpResource::getUrl('view', ['record' => $record]));
            });
    }
}
