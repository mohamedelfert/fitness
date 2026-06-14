<?php

namespace Modules\AiOrchestration\Contracts;

use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;

/**
 * The single seam between the orchestration layer and any LLM provider (ADR-004).
 * Swapping Claude ⇄ a fallback gateway is a binding change, nothing more. Tests bind
 * a scripted fake; the real adapter graduates from the spike once Q5 (provider key)
 * lands. Keeping this interface thin is what makes the provider-agnostic claim true.
 */
interface LlmGateway
{
    public function complete(LlmRequest $request): LlmResult;
}
