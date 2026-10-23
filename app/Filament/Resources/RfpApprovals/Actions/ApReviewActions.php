<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\ApprovalStep;
use App\Models\Rfp\Rfp;
use App\Services\Rfp\ApprovalEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Step 2, AP Processor first review (legacy "Endorse" / "Revise"): endorse the RFP into
 * the approval matrix. Returning it is StepActions::returnForRevision(); AP cannot
 * reject or cancel at this step.
 */
class ApReviewActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::endorse()];
    }

    public static function endorse(): Action
    {
        return Action::make('endorse')
            ->label('Endorse')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Rfp $record): bool => static::isReviewing($record))
            ->modalHeading(fn (Rfp $record): string => "Endorse {$record->rfp_number}?")
            ->modalDescription(fn (Rfp $record): string => match (true) {
                $record->is_pre_approved => 'It is pre-approved, so it skips the approval matrix and goes to AP processing.',
                (bool) config('rfp.auto_approve_matrix') => 'Matrix approval is not live yet, so the matrix is auto-approved and it goes to AP processing.',
                default => 'It goes to the approval matrix.',
            })
            ->schema([
                Textarea::make('remarks')
                    ->label('Remarks (optional)')
                    ->rows(3),
            ])
            ->modalSubmitActionLabel('Endorse')
            ->action(function (Rfp $record, array $data, Action $action): void {
                app(ApprovalEngine::class)->approve($record, auth()->user(), $data['remarks'] ?? null, verb: 'Endorsed');

                Notification::make()
                    ->title("{$record->rfp_number} endorsed")
                    ->body("Now: {$record->status->getLabel()} · {$record->currentApproverLabel()}")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }

    /**
     * Whether the signed-in user is the AP review step of this RFP right now.
     */
    protected static function isReviewing(Rfp $rfp): bool
    {
        return static::step($rfp)?->status === RfpStatus::ForApReview;
    }

    protected static function step(Rfp $rfp): ?ApprovalStep
    {
        $user = auth()->user();

        return $user ? app(ApprovalEngine::class)->actionableStep($rfp, $user) : null;
    }
}
