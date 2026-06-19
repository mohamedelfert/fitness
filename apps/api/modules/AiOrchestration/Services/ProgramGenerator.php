<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\ContraindicationScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;

/**
 * AI program generation (FR-AI-001). The safety sandwich (FR-AI-007 / INV-005) lives in
 * AiGenerator; this supplies the training specifics — the exercise-library RAG request, the
 * `workouts` shape, slug resolution, the ContraindicationScanner, and persisting the
 * programs→workouts→workout_exercises graph. INV-005: a Program persists only via finalize(),
 * reached only after the post-eval clears the output.
 */
class ProgramGenerator extends AiGenerator
{
    private ?Collection $candidates = null;

    public function __construct(LlmGateway $gateway, ContraindicationScanner $scanner)
    {
        parent::__construct($gateway, $scanner);
    }

    public function generate(Person $person): Program
    {
        return $this->runLoop($person);
    }

    protected function feature(): string
    {
        return 'program';
    }

    protected function exhaustedMessage(): string
    {
        return 'Could not generate a safe program for your profile. Please try again.';
    }

    protected function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['workouts']) || ! is_array($decoded['workouts'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every prescribed slug to a real Exercise. Returns null if the model hallucinated
     * a slug that isn't in the library (caller treats it as a failed attempt).
     *
     * @return Collection<string, Exercise>|null keyed by slug
     */
    protected function resolve(array $parsed, array $context): ?Collection
    {
        $slugs = collect($parsed['workouts'])
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

    /** Persist the validated plan as a Program graph in one transaction. */
    protected function finalize(Person $person, array $parsed, Collection $resolved, array $context): Program
    {
        return DB::transaction(function () use ($person, $parsed, $resolved) {
            $program = Program::create([
                'person_id' => $person->id,
                'source' => 'ai',
                'name' => (string) ($parsed['name'] ?? 'AI Program'),
                'start_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            foreach (array_values($parsed['workouts']) as $wIndex => $w) {
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
        });
    }

    /**
     * Assemble the RAG-grounded, structured-output request. Grounding the model on the real
     * exercise library (NFR-AI-003) is what keeps slug hallucination rare; the safety post-eval
     * catches the rest. Untested by the fake gateway — kept minimal and real for the Claude
     * adapter (Q5).
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>  $avoid
     */
    protected function buildRequest(Person $person, array $context, array $avoid, string $tier): LlmRequest
    {
        $profile = AiInputProfile::for($person);
        $this->candidates ??= Exercise::query()->orderBy('name')->limit(200)->get(['id', 'slug', 'name']);

        $library = $this->candidates->map(fn (Exercise $e) => $e->slug.' — '.$e->name)->implode("\n");

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
