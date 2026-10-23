<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\Rfp;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;

/**
 * Step 6, AP Manager, the final accounting gate (legacy accounting level 3, "Final
 * Status: Complete"): checks the tax entries (withholding tax only; the input tax is
 * locked), sets the payment type (IDS / Non-IDS) and completes the accounting review,
 * which sends the RFP to Treasury. Cancel and Return to AP Processor are StepActions.
 */
class ApManagerActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::complete()];
    }

    public static function complete(): Action
    {
        return ApProcessingActions::taxCodesAction(
            name: 'managerComplete',
            label: 'Final review',
            submitLabel: 'Complete and send to Treasury',
            verb: 'Completed',
            lockInputTax: true,
            extraSchema: [
                Toggle::make('is_ids')
                    ->label('IDS')
                    ->helperText('Payment type. Off means Non-IDS (manual).')
                    ->default(true),
            ],
            rfpAttributes: fn (array $data): array => ['is_ids' => (bool) ($data['is_ids'] ?? true)],
        )
            ->visible(function (Rfp $record): bool {
                $step = StepActions::builtStep($record);

                return $step?->status === RfpStatus::ForAccountingApproval && $step->role === RoleName::ApManager;
            })
            ->fillForm(fn (Rfp $record): array => [...ApProcessingActions::linesFor($record), 'is_ids' => $record->is_ids])
            ->modalDescription('Final accounting check. You may change the withholding tax; the input tax is locked (return it to the AP Processor to change it). Gross amounts include VAT.');
    }
}
