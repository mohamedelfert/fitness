<?php

namespace Modules\AiOrchestration\Support;

/**
 * The provider-agnostic result of an AI Brain call. `text` is the raw model output
 * (expected to be JSON for structured features); token counts + model id feed the
 * cost/latency meter recorded in `ai_interactions` (DATABASE_DESIGN.md §2.5).
 */
final class LlmResult
{
    public function __construct(
        public readonly string $text,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly string $model,
    ) {}
}
