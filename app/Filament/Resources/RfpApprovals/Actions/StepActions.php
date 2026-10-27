<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\ApprovalStep;
use App\Models\Rfp\Rfp;
use App\Services\Rfp\ApprovalEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions any step may allow (per its workflow stage): return to the requestor, return
 * to an earlier stage, and cancel. Offered only on the steps built so far.
 */
class StepActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::returnToStage(), static::returnForRevision(), static::cancel()];
    }

    public static function returnToStage(): Action
    {
        return Action::make('returnToStage')
            ->label(fn (Rfp $record): string => 'Return to '.(static::returnTarget($record) ?? 'an earlier step'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Rfp $record): bool => static::builtStep($record)?->return_to_position !== null)
            ->modalHeading(fn (Rfp $record): string => "Return {$record->rfp_number} to ".static::returnTarget($record).'?')
            ->modalDescription('It is worked again from that step, then comes back through every step after it.')
            ->schema([
                Textarea::make('remarks')
                    ->label('What needs to be fixed')
                    ->required()
                    ->rows(3),
            ])
            ->modalSubmitActionLabel('Return')
            ->action(function (Rfp $record, array $data, Action $action): void {
                $target = static::returnTarget($record);
                app(ApprovalEngine::class)->returnToStage($record, auth()->user(), $data['remarks']);

                Notification::make()
                    ->title("{$record->rfp_number} returned to {$target}")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }

    public static function returnForRevision(): Action
    {
        return Action::make('returnForRevision')
            ->label('Return for revision')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Rfp $record): bool => (bool) static::builtStep($record)?->can_return_to_requestor)
            ->modalHeading(fn (Rfp $record): string => "Return {$record->rfp_number} to the requestor?")
            ->modalDescription('The requestor fixes it and resubmits it.')
            ->schema(fn (Rfp $record): array => [
                Textarea::make('remarks')
                    ->label('AP remarks')
                    ->helperText('Tell the requestor what to fix. They see this on the request.')
                    ->required()
                    ->rows(3),
                Toggle::make('keep_matrix_approval')
                    ->label('Keep the matrix approval')
                    ->helperText('If the requestor does not change the amount, the resubmitted request skips the approval matrix and comes straight back to AP processing.')
                    ->visible(static::matrixApproved($record)),
            ])
            ->modalSubmitActionLabel('Return')
            ->action(function (Rfp $record, array $data, Action $action): void {
                app(ApprovalEngine::class)->returnToRequestor($record, auth()->user(), $data['remarks'], (bool) ($data['keep_matrix_approval'] ?? false));

                Notification::make()
                    ->title("{$record->rfp_number} returned to {$record->requestor?->name}")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel request')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Rfp $record): bool => (bool) static::builtStep($record)?->can_cancel)
            ->modalHeading(fn (Rfp $record): string => "Cancel {$record->rfp_number}?")
            ->modalDescription('The request is closed and cannot be resubmitted. The requestor is told why.')
            ->schema([
                Textarea::make('remarks')
                    ->label('Reason for cancelling')
                    ->required()
                    ->rows(3),
            ])
            ->modalSubmitActionLabel('Cancel request')
            ->action(function (Rfp $record, array $data, Action $action): void {
                app(ApprovalEngine::class)->cancel($record, auth()->user(), $data['remarks']);

                Notification::make()
                    ->title("{$record->rfp_number} cancelled")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }

    /**
     * The signed-in user's active step on this RFP, when its screen is built: Step 2 AP
     * review, Step 4 AP processing, Step 5 AP Supervisor, Step 6 AP Manager, Treasury.
     */
    public static function builtStep(Rfp $rfp): ?ApprovalStep
    {
        $user = auth()->user();
        $step = $user ? app(ApprovalEngine::class)->actionableStep($rfp, $user) : null;

        $isBuilt = match ($step?->status) {
            RfpStatus::ForApReview, RfpStatus::ForApProcessing, RfpStatus::ForTreasury => true,
            RfpStatus::ForAccountingApproval => in_array($step->role, [RoleName::ApSupervisor, RoleName::ApManager], true),
            default => false,
        };

        return $isBuilt ? $step : null;
    }

    /**
     * The label of the step the user's step returns to, e.g. "AP Processor".
     */
    protected static function returnTarget(Rfp $rfp): ?string
    {
        $position = static::builtStep($rfp)?->return_to_position;

        return $position === null ? null : $rfp->approvalSteps()
            ->where('round', $rfp->approval_round)
            ->where('stage_position', $position)
            ->value('label');
    }

    /**
     * Whether this round's matrix is already approved (or skipped), so it can be kept.
     */
    protected static function matrixApproved(Rfp $rfp): bool
    {
        $matrix = $rfp->approvalSteps()->where('round', $rfp->approval_round)->where('kind', RfpStageKind::Matrix)->get();

        return $matrix->isNotEmpty()
            && $matrix->every(fn (ApprovalStep $step): bool => in_array($step->state, [RfpApprovalStepState::Approved, RfpApprovalStepState::Skipped], true));
    }
}
