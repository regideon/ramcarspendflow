<?php

namespace App\Services\P2p\Escalation;

/**
 * A custom action attached to an escalation route step, replacing the generic
 * "Advance" button (e.g. the Warehouse "Reverse & re-GR" hop).
 */
interface StepAction
{
    public function handle(StepActionContext $context): StepActionCompletion;
}
