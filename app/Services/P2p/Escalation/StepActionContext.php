<?php

namespace App\Services\P2p\Escalation;

use App\Models\P2p\Escalation;
use App\Models\User;

/**
 * Everything a custom escalation-hop action needs to run.
 */
final readonly class StepActionContext
{
    public function __construct(
        public Escalation $escalation,
        public User $user,
        public ?string $remarks = null,
    ) {}
}
