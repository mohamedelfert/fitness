<?php

namespace Modules\AiOrchestration\Gateways;

use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use RuntimeException;

/**
 * The default binding until the real provider adapter lands (blocked on Q5 — see
 * `docs/AI_BRAIN_SPIKE.md` §6). Fails loud and clear rather than silently producing
 * garbage. Tests override this binding with a scripted fake gateway.
 */
final class UnconfiguredLlmGateway implements LlmGateway
{
    public function complete(LlmRequest $request): LlmResult
    {
        throw new RuntimeException(
            'No LLM provider is configured. Bind '.LlmGateway::class.' to a real adapter '
            .'(Claude-primary) once the provider key (Q5) is available.'
        );
    }
}
