<?php

namespace App\Services\P2p\Escalation;

use App\Models\P2p\EscalationReasonStep;

/**
 * Warehouse hop action: reverse the goods receipt in SAP and post a replacement.
 *
 * The SAP write side is deferred with the integration (docs/p2p/07-sap-integration.md),
 * so this currently records the intent and lets the escalation advance; when the SAP
 * service lands it will call it here.
 */
class ReverseAndReGrAction implements StepAction
{
    public function handle(StepActionContext $context): StepActionCompletion
    {
        // TODO(SAP): reverse + re-GR via the SAP write service, then let the sync pull
        // the replacement GR (ReversalDetectionService). Until the write side lands,
        // recording the intent settles the hop: the warehouse's reversal closes the
        // escalation rather than walking the route on. Return
        // `StepActionCompletion::skipped()` when there is nothing to reverse.
        return StepActionCompletion::resolved('Reverse & re-GR recorded.');
    }

    public static function key(): string
    {
        return EscalationReasonStep::ACTION_REVERSE_REGR;
    }
}
