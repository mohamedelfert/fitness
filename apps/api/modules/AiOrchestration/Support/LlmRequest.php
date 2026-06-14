<?php

namespace Modules\AiOrchestration\Support;

/**
 * A provider-agnostic request to the AI Brain (ADR-004 seam). Carries the assembled
 * RAG-grounded prompt and the JSON schema the model must fill. The concrete adapter
 * (Claude-primary, per `docs/AI_BRAIN_SPIKE.md`, lands with Q5) translates this into
 * its own wire format; the orchestration layer never speaks a vendor dialect.
 */
final class LlmRequest
{
    /**
     * @param  array<string, mixed>  $schema  JSON schema for the structured output
     * @param  array<string, mixed>  $metadata  free-form context for logging/routing
     */
    public function __construct(
        public readonly string $system,
        public readonly string $prompt,
        public readonly array $schema = [],
        public readonly string $tier = 'strong',
        public readonly string $feature = 'program',
        public readonly array $metadata = [],
    ) {}
}
