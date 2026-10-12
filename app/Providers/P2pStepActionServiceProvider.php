<?php

namespace App\Providers;

use App\Services\P2p\Escalation\ReverseAndReGrAction;
use App\Services\P2p\Escalation\StepActionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the P2P escalation step actions.
 */
class P2pStepActionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StepActionRegistry::class, function (): StepActionRegistry {
            $registry = new StepActionRegistry;
            $registry->register(ReverseAndReGrAction::key(), new ReverseAndReGrAction);

            return $registry;
        });
    }
}
