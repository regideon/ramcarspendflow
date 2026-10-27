<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\Rfp;
use Filament\Actions\Action;

/**
 * Step 5, AP Supervisor (legacy accounting level 2): reviews the tax codes the AP
 * Processor booked, may adjust them, and sends the RFP to the AP Manager. Returning it
 * to the AP Processor is StepActions::returnToStage(); no reject or cancel here.
 */
class ApSupervisorActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::approve()];
    }

    public static function approve(): Action
    {
        return ApProcessingActions::taxCodesAction('supervisorApprove', 'Review and approve', 'Approve and send to AP Manager', 'Approved')
            ->visible(function (Rfp $record): bool {
                $step = StepActions::builtStep($record);

                return $step?->status === RfpStatus::ForAccountingApproval && $step->role === RoleName::ApSupervisor;
            })
            ->modalDescription('Check the tax codes the AP Processor booked; change any that are wrong. Gross amounts include VAT, and each currency must still total the same.');
    }
}
