<?php

namespace App\Filament\Resources\RfpApprovals;

use App\Filament\Resources\RfpApprovals\Pages\ReviewRfpApproval;

/**
 * Puts the redesigned RFP screen under the new queue's URL: /hub/rfp/approvals/{record}
 * (the queue itself is the App\Filament\Pages\Rfp\Approvals page at /hub/rfp/approvals).
 * Same records and rules as RfpApprovalResource; no menu item and no list of its own.
 */
class RfpApprovalReviewResource extends RfpApprovalResource
{
    protected static ?string $slug = 'rfp/approvals';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isGloballySearchable = false;

    public static function getPages(): array
    {
        return [
            'view' => ReviewRfpApproval::route('/{record}'),
        ];
    }
}
