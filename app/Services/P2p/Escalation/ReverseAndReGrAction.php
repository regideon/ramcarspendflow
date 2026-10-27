<?php

namespace App\Services\P2p\Escalation;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Contracts\P2p\SapReversalSource;
use App\Models\P2p\EscalationReasonStep;
use App\Services\P2p\P2pGoodReceiptReversalService;
use App\Support\GoodReceiptNumber;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Warehouse hop action: reverse the goods receipt in SAP and post a replacement.
 *
 * The SAP write side is deferred, but the replacement SAP already recorded is
 * read from the reversal source, applied to the GR, and the request is moved onto
 * the replacement re-GR. The warehouse can still type remarks on the action.
 */
class ReverseAndReGrAction implements StepAction
{
    public function handle(StepActionContext $context): StepActionCompletion
    {
        $escalation = $context->escalation->loadMissing(['goodReceiptState', 'invoice']);
        $state = $escalation->goodReceiptState;

        if ($state === null || $escalation->invoice === null) {
            return StepActionCompletion::resolved('No goods receipt is attached to this escalation.');
        }

        $base = GoodReceiptNumber::base($state->good_receipt_number);
        $reversalDocument = null;
        $replacement = null;

        try {
            $source = app(SapPurchaseOrderSource::class);

            if ($source instanceof SapReversalSource) {
                $detected = $source->reversalsForPurchaseOrder($state->purchase_order);
                $reversalDocument = $detected['reversed'][$base] ?? null;
                $replacement = $detected['replacements'][$base] ?? null;
            }
        } catch (Throwable) {
            // SAP unavailable: the hop can still be advanced without a reversal.
        }

        if ($reversalDocument === null && $replacement === null) {
            Notification::make()
                ->title('No reversal detected')
                ->body('SAP has not returned a reversal or replacement GR for '.$base.'. The escalation will advance without one.')
                ->warning()
                ->send();

            return StepActionCompletion::resolved('No reversal detected.');
        }

        $reversal = app(P2pGoodReceiptReversalService::class);
        $reversal->apply($state, $reversalDocument, $replacement, cancelEscalations: false);

        if ($replacement !== null) {
            $reversal->substituteReplacementGrs($escalation->invoice, $state->good_receipt_number, [$replacement]);
        }

        Notification::make()
            ->title('Reversal recorded')
            ->body('Reversed '.$base
                .($reversalDocument ? ' (document '.$reversalDocument.')' : '')
                .($replacement ? '; replacement GR '.$replacement.'.' : '.'))
            ->success()
            ->send();

        return StepActionCompletion::resolved('Reverse & re-GR recorded.');
    }

    public static function key(): string
    {
        return EscalationReasonStep::ACTION_REVERSE_REGR;
    }
}
