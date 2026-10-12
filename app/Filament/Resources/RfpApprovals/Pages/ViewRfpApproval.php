<?php

namespace App\Filament\Resources\RfpApprovals\Pages;

use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use Filament\Resources\Pages\ViewRecord;

/**
 * Approve / Return / Reject / Process actions come with the approval engine (roadmap 2.4).
 */
class ViewRfpApproval extends ViewRecord
{
    protected static string $resource = RfpApprovalResource::class;

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
