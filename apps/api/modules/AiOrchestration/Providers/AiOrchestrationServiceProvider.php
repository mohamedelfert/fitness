<?php

namespace Modules\AiOrchestration\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Gateways\UnconfiguredLlmGateway;

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
}
