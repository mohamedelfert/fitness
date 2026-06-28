<?php

namespace Modules\AiOrchestration\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Gateways\UnconfiguredLlmGateway;
use Modules\AiOrchestration\Listeners\GrantFreeAiCredits;
use Modules\Identity\Events\OnboardingCompleted;

/**
 * Wires the AI Brain seam (ADR-004). The default LlmGateway binding fails loud until a
 * real provider adapter is registered here (blocked on Q5; tests bind a scripted fake).
 * Swapping providers is a one-line change to this binding — nothing else moves.
 */
class AiOrchestrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmGateway::class, UnconfiguredLlmGateway::class);
    }

    public function boot(): void
    {
        // Fund the free AICredit starter balance when a Person finishes onboarding. Identity
        // emits the event; AiOrchestration reacts — the dependency points this way only.
        Event::listen(OnboardingCompleted::class, GrantFreeAiCredits::class);
    }
}
