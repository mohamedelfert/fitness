<?php

namespace Modules\AiOrchestration\Services;

use Modules\AiOrchestration\Models\AiInteraction;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;

/**
 * Writes one ai_interactions audit/cost row per AI Brain call (DATABASE_DESIGN §2.5), and owns
 * the cost formula (INV-006). Extracted so the AiGenerator base and the non-base generators
 * (daily recommendation, coach chat) record calls — and price them — through one place rather
 * than each carrying its own copy. Returns the row so callers can surface its id.
 */
class AiInteractionLogger
{
    public function log(Person $person, string $feature, LlmResult $result, string $verdict, int $latencyMs, string $tier): AiInteraction
    {
        return AiInteraction::create([
            'person_id' => $person->id,
            'feature' => $feature,
            'model' => $result->model,
            'tier' => $tier,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'cost_micros' => $this->costMicros($result),
            'latency_ms' => $latencyMs,
            'safety_verdict' => $verdict,
        ]);
    }

    /** Cost in integer micro-USD (INV-006). Unknown/stub models price at 0 until Q5. */
    private function costMicros(LlmResult $result): int
    {
        $rates = config('ai.pricing.'.$result->model, config('ai.pricing.default'));

        return (int) round(
            $result->tokensIn / 1000 * ($rates['in'] ?? 0)
            + $result->tokensOut / 1000 * ($rates['out'] ?? 0)
        );
    }
}
