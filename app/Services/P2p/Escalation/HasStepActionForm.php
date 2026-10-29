<?php

namespace App\Services\P2p\Escalation;

use App\Models\P2p\Escalation;

/**
 * A custom escalation-hop action that supplies its own modal fields instead of
 * the generic remarks-only advance form.
 */
interface HasStepActionForm
{
    /**
     * @return array<int, mixed>
     */
    public function schema(Escalation $escalation): array;

    public function modalHeading(Escalation $escalation): string;

    public function modalDescription(Escalation $escalation): string;

    public function submitLabel(Escalation $escalation): string;

    public function label(Escalation $escalation): string;
}
