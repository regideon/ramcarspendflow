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
        public bool $handled = true,
    ) {}

    public static function done(string $message = ''): self
    {
        return new self($message, handled: true);
    }

    /**
     * The action did not act (nothing to reverse, user cancelled): keep the
     * escalation on its current hop instead of advancing it.
     */
    public static function skipped(string $message = ''): self
    {
        return new self($message, handled: false);
    }

    /**
     * The action replaced the generic Advance: close the escalation instead.
     */
    public static function resolved(string $message = ''): self
    {
        return new self($message, resolved: true, handled: true);
    }
}
