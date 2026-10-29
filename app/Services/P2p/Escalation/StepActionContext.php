<?php

namespace App\Services\P2p\Escalation;

use App\Models\P2p\Escalation;
use App\Models\User;

/**
 * Everything a custom escalation-hop action needs to run.
 */
final readonly class StepActionContext
{
    /**
     * @param  array<string, mixed>  $data  the custom action modal's submitted state
     */
    public function __construct(
        public Escalation $escalation,
        public ?User $user = null,
        public ?string $remarks = null,
        public array $data = [],
    ) {}
}
