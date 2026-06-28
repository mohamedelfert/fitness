<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Wearables\Models\WearableStream;

/**
 * AI recovery tips (FR-AI-005). Advisory recovery guidance grounded in the Person's recent
 * wearable data (sleep/HRV/resting-HR/steps) + an optional soreness signal. Like the daily
 * recommendation it prescribes no library entities, so it is NOT an AiGenerator subclass and
 * runs no contraindication sandwich — safety is by construction (the prompt forbids medical
 * advice / diagnosis). Generated fresh on each call so the advice reflects the latest data;
 * cost is bounded by metering, not by caching.
 */
class RecoveryTipGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly AiInteractionLogger $logger,
    ) {}

    /**
     * @return array{message: string, interaction_id: string}
     *
     * @throws ValidationException 422 when the model returns nothing usable (never a 500)
     */
    public function generate(Person $person, ?string $soreness = null): array
    {
        $tier = (string) config('ai.recovery.tier', 'cheap');
        $request = $this->buildRequest($person, $soreness, $tier);

        $startedAt = microtime(true);
        $result = $this->gateway->complete($request);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $message = $this->extractMessage($result->text);
        $interaction = $this->logger->log($person, 'recovery', $result, $message === '' ? 'error' : 'passed', $latencyMs, $tier);

        if ($message === '') {
            throw ValidationException::withMessages(['recovery' => 'Could not produce recovery advice. Please try again.']);
        }

        return ['message' => $message, 'interaction_id' => $interaction->id];
    }

    /** Accept either structured `{ "message": "…" }` or a bare line. */
    private function extractMessage(string $text): string
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return trim($decoded['message']);
        }

        return trim($text);
    }

    private function buildRequest(Person $person, ?string $soreness, string $tier): LlmRequest
    {
        $system = 'You are a recovery coach. From the athlete profile, their recent wearable signals '
            .'(sleep, HRV, resting heart rate, steps) and reported soreness, give one short (2-3 '
            .'sentence) recovery recommendation: whether to train as planned, train lightly, or rest, '
            .'plus a sleep/hydration nudge. Do NOT diagnose or give medical advice — for pain or '
            .'injury advise seeing a qualified professional. Respond with JSON: {"message": "…"}.';

        $prompt = 'Athlete profile:'."\n".json_encode(AiInputProfile::for($person), JSON_PRETTY_PRINT)
            ."\n\nRecent wearables: ".$this->wearableSummary($person)
            ."\n\nReported soreness: ".($soreness ?? 'not reported');

        return new LlmRequest(system: $system, prompt: $prompt, tier: $tier, feature: 'recovery');
    }

    /** Latest reading per metric over the last week, or a note that there's no data. */
    private function wearableSummary(Person $person): string
    {
        $latest = WearableStream::where('person_id', $person->id)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->orderByDesc('recorded_at')
            ->get()
            ->groupBy('metric')
            ->map(fn ($rows, $metric) => $metric.'='.$rows->first()->value);

        return $latest->isEmpty() ? 'no recent wearable data' : $latest->values()->implode(', ');
    }
}
