<?php

namespace App\Services\P2p\Escalation;

/**
 * Result of a custom escalation-hop action.
 */
final readonly class StepActionCompletion
{
    public function __construct(
        public string $message = '',
        public bool $resolved = false,
    ) {}

    public static function done(string $message = ''): self
    {
        return new self($message);
    }
}
