<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a stage of the RFP approval workflow does. See docs/12-rfp-dynamic-workflow.md.
 */
enum RfpStageKind: string implements HasLabel
{
    case Approval = 'approval'; // one role approves
    case Matrix = 'matrix'; // expands into the approval matrix tier for the RFP
    case ApProcessing = 'ap_processing'; // AP Processor books the entries and taxes
    case Payment = 'payment'; // Treasury releases the payment; always last

    public function getLabel(): string
    {
        return match ($this) {
            self::Approval => 'Approval',
            self::Matrix => 'Approval matrix',
            self::ApProcessing => 'AP processing',
            self::Payment => 'Payment release',
        };
    }

    /**
     * Whether the stage is done by one configured role (the matrix brings its own roles).
     */
    public function hasRole(): bool
    {
        return $this !== self::Matrix;
    }
}
