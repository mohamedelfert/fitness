<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\WeeklyReport;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Analytics\Services\AdherenceAnalyzer;
use Modules\Analytics\Services\ProgressAnalyzer;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;

/**
 * AI weekly report (FR-AN-005). An advisory narrative grounded in the Person's progress +
 * adherence read-models. Like the daily recommendation it prescribes no library entities, so it
 * is NOT an AiGenerator subclass and runs no contraindication sandwich — safety is by
 * construction (the prompt forbids specific exercise/medical prescriptions). The controller keys
 * idempotency on person+iso_week, so this only runs on a cache miss: one call, one logged
 * ai_interactions row, one persisted row for the week.
 */
class WeeklyReportGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly AiInteractionLogger $logger,
        private readonly ProgressAnalyzer $progress,
        private readonly AdherenceAnalyzer $adherence,
    ) {}

    /** Generate and persist the report for `$isoWeek`. Throws 422 on unusable output (never 500). */
    public function generate(Person $person, string $isoWeek, string $weekStart): WeeklyReport
    {
        $tier = (string) config('ai.weekly_report.tier', 'default');
        $request = $this->buildRequest($person, $tier);

        $startedAt = microtime(true);
        $result = $this->gateway->complete($request);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $summary = $this->extractSummary($result->text);
        $this->logger->log($person, 'weekly_report', $result, $summary === '' ? 'error' : 'passed', $latencyMs, $tier);

        if ($summary === '') {
            throw ValidationException::withMessages(['weekly_report' => 'Could not produce a weekly report. Please try again.']);
        }

        return WeeklyReport::create([
            'person_id' => $person->id,
            'iso_week' => $isoWeek,
            'week_start' => $weekStart,
            'summary' => $summary,
            'model' => $result->model,
        ]);
    }

    /** Accept either structured `{ "summary": "…" }` or a bare paragraph. */
    private function extractSummary(string $text): string
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['summary']) && is_string($decoded['summary'])) {
            return trim($decoded['summary']);
        }

        return trim($text);
    }

    private function buildRequest(Person $person, string $tier): LlmRequest
    {
        $system = 'You are a fitness coach writing a short weekly progress report (3-5 sentences) for '
            .'the athlete. Summarise their training adherence, body/metric trends and goal progress '
            .'from the data, give specific encouragement, and name ONE focus for next week. Do NOT '
            .'prescribe specific exercises, loads, sets, medications or medical advice — structured '
            .'plans come from elsewhere. Respond with JSON: {"summary": "…"}.';

        $prompt = "Athlete profile:\n".json_encode(AiInputProfile::for($person), JSON_PRETTY_PRINT)
            ."\n\nProgress (FR-AN-001):\n".json_encode($this->progress->for($person), JSON_PRETTY_PRINT)
            ."\n\nAdherence (FR-AN-002):\n".json_encode($this->adherence->for($person), JSON_PRETTY_PRINT);

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: ['type' => 'object', 'required' => ['summary'], 'properties' => ['summary' => ['type' => 'string']]],
            tier: $tier,
            feature: 'weekly_report',
        );
    }
}
