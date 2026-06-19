<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\AiInteraction;
use Modules\AiOrchestration\Support\ContraindicationScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Training\Models\Exercise;

/**
 * AI exercise-alternatives (FR-AI-003) — the third AiOrchestration generator. It runs the same
 * safety sandwich as program/meal-plan generation (a contraindicated swap is an INV-005 hazard)
 * but its *shape* is the odd one out: extra inputs (the exercise to replace + a count), no
 * persistence, and it returns a ranked list of suggestions rather than a saved graph. It is
 * kept standalone for now; once this third concrete case exists, the loop/log/cost commonality
 * shared with ProgramGenerator/MealPlanGenerator is worth extracting to a base (handoff note).
 *
 * Uses a cheap model tier (config ai.exercise_alternatives.tier) — swaps don't need full-plan
 * reasoning, the margin lever in ARCH §6.
 */
class ExerciseAlternativeGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly ContraindicationScanner $scanner,
    ) {}

    /**
     * @return Collection<int, array{exercise: Exercise, rationale: ?string}> safe, ordered swaps
     */
    public function generate(Person $person, Exercise $source, int $count = 3): Collection
    {
        $profile = AiInputProfile::for($person);
        $candidates = Exercise::query()->whereKeyNot($source->id)->orderBy('name')->limit(200)
            ->get(['id', 'slug', 'name', 'primary_muscles', 'equipment']);
        $tier = (string) config('ai.exercise_alternatives.tier', 'cheap');
        $maxAttempts = (int) config('ai.exercise_alternatives.max_attempts', 2);

        $avoid = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $request = $this->buildRequest($profile, $source, $candidates, $count, $avoid, $tier);

            $startedAt = microtime(true);
            $result = $this->gateway->complete($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $parsed = $this->parse($result->text);
            if ($parsed === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // malformed output — never a 500; retry then give up
            }

            $resolved = $this->resolveExercises($parsed, $source);
            if ($resolved === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // hallucinated slug — retry then give up
            }

            $unsafe = $this->scanner->unsafeSlugs($person, $resolved->values());
            if ($unsafe !== []) {
                $this->log($person, $result, 'rejected', $latencyMs, $tier);
                $avoid = array_values(array_unique([...$avoid, ...$unsafe]));

                continue; // contraindicated suggestion — regenerate avoiding these movements
            }

            $this->log($person, $result, 'passed', $latencyMs, $tier);

            return $this->finalize($parsed, $resolved);
        }

        // Exhausted attempts without safe, valid suggestions. Nothing is persisted regardless.
        throw ValidationException::withMessages([
            'exercise_alternatives' => 'Could not find safe alternatives for this exercise. Please try again.',
        ]);
    }

    /** Decode model output to a suggestions array, or null if it isn't a usable shape. */
    private function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['alternatives']) || ! is_array($decoded['alternatives'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every suggested slug to a real Exercise, dropping the source itself. Returns null
     * if the model hallucinated a slug or suggested nothing usable (treated as a failed attempt).
     *
     * @return Collection<string, Exercise>|null keyed by slug
     */
    private function resolveExercises(array $parsed, Exercise $source): ?Collection
    {
        $slugs = collect($parsed['alternatives'])
            ->pluck('exercise_slug')
            ->filter()
            ->unique()
            ->reject(fn ($slug) => $slug === $source->slug)
            ->values();

        if ($slugs->isEmpty()) {
            return null;
        }

        $found = Exercise::whereIn('slug', $slugs)->get()->keyBy('slug');

        return $found->count() === $slugs->count() ? $found : null;
    }

    /**
     * Pair each suggestion (in model order) with its resolved Exercise + rationale. No persistence —
     * these are proposals the member applies to a program later.
     *
     * @param  Collection<string, Exercise>  $resolved
     * @return Collection<int, array{exercise: Exercise, rationale: ?string}>
     */
    private function finalize(array $parsed, Collection $resolved): Collection
    {
        return collect($parsed['alternatives'])
            ->filter(fn ($a) => isset($a['exercise_slug'], $resolved[$a['exercise_slug']]))
            ->map(fn ($a) => [
                'exercise' => $resolved[$a['exercise_slug']],
                'rationale' => isset($a['rationale']) ? (string) $a['rationale'] : null,
            ])
            ->values();
    }

    private function log(Person $person, LlmResult $result, string $verdict, int $latencyMs, string $tier): void
    {
        AiInteraction::create([
            'person_id' => $person->id,
            'feature' => 'exercise_alternatives',
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

    /**
     * Ground the model on the real library + the exercise being swapped and the athlete's
     * equipment/injuries, asking for similar-stimulus alternatives. Untested by the fake gateway —
     * kept minimal and real for the Claude adapter (Q5).
     *
     * @param  Collection<int, Exercise>  $candidates
     * @param  list<string>  $avoid
     */
    private function buildRequest(array $profile, Exercise $source, Collection $candidates, int $count, array $avoid, string $tier): LlmRequest
    {
        $library = $candidates->map(fn (Exercise $e) => $e->slug.' — '.$e->name)->implode("\n");

        $system = 'You are a certified strength & conditioning coach. Suggest alternative exercises '
            .'that train a similar movement pattern and muscles to the target exercise, respecting '
            .'the athlete\'s available equipment and injuries. Use ONLY exercises from the provided '
            .'library, referenced by their exact slug. Respond with JSON only, matching the given '
            .'schema — no prose.';

        $prompt = 'Find up to '.$count." safe alternatives to: {$source->slug} — {$source->name}.\n\n"
            .'Athlete profile:\n'.json_encode($profile, JSON_PRETTY_PRINT)
            ."\n\nExercise library (slug — name):\n".$library;

        if ($avoid !== []) {
            $prompt .= "\n\nA previous suggestion was rejected by the safety review. Do NOT suggest "
                .'these contraindicated exercises: '.implode(', ', $avoid).'.';
        }

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: $this->schema(),
            tier: $tier,
            feature: 'exercise_alternatives',
            metadata: ['source' => $source->slug, 'avoid' => $avoid],
        );
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['alternatives'],
            'properties' => [
                'alternatives' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['exercise_slug'],
                        'properties' => [
                            'exercise_slug' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
