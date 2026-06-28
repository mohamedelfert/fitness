<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\DailyRecommendation;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;

/**
 * AI daily recommendation (FR-AI-004). Deliberately NOT an AiGenerator subclass: a daily nudge
 * prescribes no library entities, so the resolve→contraindication-scan safety sandwich has
 * nothing to act on — forcing it would mean stubbing those hooks. Safety is instead by
 * construction: the system prompt forbids specific exercise/medical prescriptions (plans come
 * from the safety-gated generators), so the output is advisory only.
 *
 * One call, one logged ai_interactions row, one persisted row for the day (the controller keys
 * idempotency on person+date so this only runs on a cache miss).
 */
class DailyRecommendationGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly AiInteractionLogger $logger,
    ) {}

    /** Generate and persist the recommendation for `$date` (Y-m-d). Throws 422 on unusable output. */
    public function generate(Person $person, string $date): DailyRecommendation
    {
        $tier = (string) config('ai.daily_recommendation.tier', 'cheap');
        $request = $this->buildRequest($person, $tier);

        $startedAt = microtime(true);
        $result = $this->gateway->complete($request);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $message = $this->extractMessage($result->text);

        $this->log($person, $result, $message === '' ? 'error' : 'passed', $latencyMs, $tier);

        if ($message === '') {
            // Never a 500; an unusable line just isn't materialised (and isn't charged).
            throw ValidationException::withMessages(['daily_recommendation' => 'Could not produce a recommendation. Please try again.']);
        }

        return DailyRecommendation::create([
            'person_id' => $person->id,
            'rec_date' => $date,
            'message' => $message,
            'model' => $result->model,
        ]);
    }

    /** Accept either structured `{ "message": "…" }` or a bare line — a nudge needn't be JSON. */
    private function extractMessage(string $text): string
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return trim($decoded['message']);
        }

        return trim($text);
    }

    /**
     * Ground the nudge on the athlete profile. Kept minimal and real for the Claude adapter (Q5);
     * richer RAG (recent sessions/PRs/streaks) is deferred — the fake gateway ignores this anyway.
     */
    private function buildRequest(Person $person, string $tier): LlmRequest
    {
        $profile = AiInputProfile::for($person);

        $system = 'You are an encouraging fitness coach writing one short, personalised daily '
            .'recommendation (2-3 sentences) for the athlete: motivation, a behavioural nudge, or '
            .'a recovery/consistency tip grounded in their profile and goals. Do NOT prescribe '
            .'specific exercises, loads, sets, medications, or medical advice — structured plans '
            .'come from elsewhere. Respond with JSON: {"message": "…"}.';

        $prompt = "Athlete profile:\n".json_encode($profile, JSON_PRETTY_PRINT);

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string']]],
            tier: $tier,
            feature: 'daily_recommendation',
        );
    }

    private function log(Person $person, LlmResult $result, string $verdict, int $latencyMs, string $tier): void
    {
        $this->logger->log($person, 'daily_recommendation', $result, $verdict, $latencyMs, $tier);
    }
}
