<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\AiInteraction;
use Modules\AiOrchestration\Support\ContraindicationScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;

/**
 * AI program generation (FR-AI-001) wrapped in the safety sandwich (FR-AI-007 / INV-005):
 *
 *   RAG context (the Person's Graph) → generate → parse → resolve to real exercises →
 *   safety post-eval → reject + regenerate on fail → persist only when clean.
 *
 * INV-005 is the hard invariant: a Program is persisted ONLY after the output passes the
 * post-eval. Every call — passed, rejected, or unparseable — is logged to ai_interactions
 * for audit/cost, and that logging is intentionally OUTSIDE the persist transaction so the
 * rejected trail survives even when generation ultimately fails (422).
 */
class ProgramGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly ContraindicationScanner $scanner,
    ) {}

    public function generate(Person $person): Program
    {
        $profile = AiInputProfile::for($person);
        $candidates = Exercise::query()->orderBy('name')->limit(200)->get(['id', 'slug', 'name']);
        $tier = (string) config('ai.program.tier', 'strong');
        $maxAttempts = (int) config('ai.program.max_attempts', 2);

        $avoid = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $request = $this->buildRequest($profile, $candidates, $avoid, $tier);

            $startedAt = microtime(true);
            $result = $this->gateway->complete($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $plan = $this->parse($result->text);
            if ($plan === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // malformed output — never a 500; retry then give up
            }

            $resolved = $this->resolveExercises($plan);
            if ($resolved === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // hallucinated slug — retry then give up
            }

            $unsafe = $this->scanner->unsafeSlugs($person, $resolved->values());
            if ($unsafe !== []) {
                $this->log($person, $result, 'rejected', $latencyMs, $tier);
                $avoid = array_values(array_unique([...$avoid, ...$unsafe]));

                continue; // contraindicated — regenerate avoiding these movements
            }

            $this->log($person, $result, 'passed', $latencyMs, $tier);

            return DB::transaction(fn () => $this->persist($person, $plan, $resolved));
        }

        // Exhausted attempts without a safe, valid plan. INV-005: nothing persisted.
        throw ValidationException::withMessages([
            'program' => 'Could not generate a safe program for your profile. Please try again.',
        ]);
    }

    /** Decode model output to a plan array, or null if it isn't a usable program object. */
    private function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['workouts']) || ! is_array($decoded['workouts'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every prescribed slug to a real Exercise. Returns null if the model
     * hallucinated a slug that isn't in the library (caller treats it as a failed attempt).
     *
     * @return Collection<string, Exercise>|null keyed by slug
     */
    private function resolveExercises(array $plan): ?Collection
    {
        $slugs = collect($plan['workouts'])
            ->flatMap(fn ($w) => collect($w['exercises'] ?? [])->pluck('exercise_slug'))
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return null;
        }

        $found = Exercise::whereIn('slug', $slugs)->get()->keyBy('slug');

        return $found->count() === $slugs->count() ? $found : null;
    }

    /** Persist the validated plan as a Program graph. Caller wraps this in a transaction. */
    private function persist(Person $person, array $plan, Collection $resolved): Program
    {
        $program = Program::create([
            'person_id' => $person->id,
            'source' => 'ai',
            'name' => (string) ($plan['name'] ?? 'AI Program'),
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        foreach (array_values($plan['workouts']) as $wIndex => $w) {
            $workout = Workout::create([
                'program_id' => $program->id,
                'day_index' => (int) ($w['day_index'] ?? $wIndex + 1),
                'name' => (string) ($w['name'] ?? 'Workout '.($wIndex + 1)),
                'ordering' => $wIndex,
            ]);

            foreach (array_values($w['exercises'] ?? []) as $eIndex => $e) {
                WorkoutExercise::create([
                    'workout_id' => $workout->id,
                    'exercise_id' => $resolved[$e['exercise_slug']]->id,
                    'order' => $eIndex,
                    'target_sets' => $e['target_sets'] ?? null,
                    'target_reps' => isset($e['target_reps']) ? (string) $e['target_reps'] : null,
                    'rest_sec' => $e['rest_sec'] ?? null,
                ]);
            }
        }

        return $program->load(['workouts.workoutExercises.exercise']);
    }

    private function log(Person $person, LlmResult $result, string $verdict, int $latencyMs, string $tier): void
    {
        AiInteraction::create([
            'person_id' => $person->id,
            'feature' => 'program',
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
     * Assemble the RAG-grounded, structured-output request. Grounding the model on the
     * real exercise library (NFR-AI-003) is what keeps slug hallucination rare; the safety
     * post-eval catches the rest. Untested by the fake gateway — kept minimal and real for
     * when the Claude adapter lands (Q5).
     *
     * @param  Collection<int, Exercise>  $candidates
     * @param  list<string>  $avoid
     */
    private function buildRequest(array $profile, Collection $candidates, array $avoid, string $tier): LlmRequest
    {
        $library = $candidates->map(fn (Exercise $e) => $e->slug.' — '.$e->name)->implode("\n");

        $system = 'You are a certified strength & conditioning coach. Generate a safe, '
            .'progressive training program personalized to the athlete profile. Use ONLY '
            .'exercises from the provided library, referenced by their exact slug. Respect '
            .'the athlete\'s equipment, experience level, and any injuries. Respond with '
            .'JSON only, matching the given schema — no prose.';

        $prompt = "Athlete profile:\n".json_encode($profile, JSON_PRETTY_PRINT)
            ."\n\nExercise library (slug — name):\n".$library;

        if ($avoid !== []) {
            $prompt .= "\n\nA previous attempt was rejected by the safety review. Do NOT "
                .'prescribe these contraindicated exercises: '.implode(', ', $avoid).'.';
        }

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: $this->schema(),
            tier: $tier,
            feature: 'program',
            metadata: ['avoid' => $avoid],
        );
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'workouts'],
            'properties' => [
                'name' => ['type' => 'string'],
                'workouts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['day_index', 'name', 'exercises'],
                        'properties' => [
                            'day_index' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'exercises' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['exercise_slug', 'target_sets', 'target_reps'],
                                    'properties' => [
                                        'exercise_slug' => ['type' => 'string'],
                                        'target_sets' => ['type' => 'integer'],
                                        'target_reps' => ['type' => 'string'],
                                        'rest_sec' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
