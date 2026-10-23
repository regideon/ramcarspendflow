<?php

namespace App\Filament\Resources\RfpApprovals\Pages;

use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\RfpApprovals\Actions\ApManagerActions;
use App\Filament\Resources\RfpApprovals\Actions\ApProcessingActions;
use App\Filament\Resources\RfpApprovals\Actions\ApReviewActions;
use App\Filament\Resources\RfpApprovals\Actions\ApSupervisorActions;
use App\Filament\Resources\RfpApprovals\Actions\StepActions;
use App\Filament\Resources\RfpApprovals\Actions\TreasuryActions;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use Filament\Resources\Pages\ViewRecord;

/**
 * The RFP as Accounting sees it, with the actions of the step the user holds now.
 * Built so far: Step 2 AP review (Endorse), Step 4 AP processing (Process tax codes) and
 * Step 5 AP Supervisor (Review and approve), Step 6 AP Manager (Final review) and Treasury
 * (Release payment), with Return / Cancel where the step allows them.
 */
class ViewRfpApproval extends ViewRecord
{
    use HasCommentsChat;

    protected static string $resource = RfpApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ApReviewActions::make(),
            ...ApProcessingActions::make(),
            ...ApSupervisorActions::make(),
            ...ApManagerActions::make(),
            ...TreasuryActions::make(),
            ...StepActions::make(),
        ];
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

    public function getSubheading(): ?string
    {
        $with = $this->getRecord()->currentApproverLabel();

        return $with ? "Currently with: {$with}" : null;
    }
}
