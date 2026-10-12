<?php

namespace App\Services\P2p\Escalation;

/**
 * Resolves an escalation step's `action` key to its handler.
 */
class StepActionRegistry
{
    /** @var array<string, StepAction> */
    private array $actions = [];

    public function register(string $key, StepAction $action): void
    {
        $this->actions[$key] = $action;
    }

    public function resolve(?string $key): ?StepAction
    {
        return $key === null ? null : ($this->actions[$key] ?? null);
    }

    public function has(string $key): bool
    {
        return isset($this->actions[$key]);
    }
}
