<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
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
 * AI plan-adjustment proposals (FR-AI-006). The twin of ExerciseAlternativeGenerator: it runs the
 * same safety sandwich via AiGenerator over the *prescribed* exercises (a contraindicated swap or
 * addition is an INV-005 hazard), reusing ContraindicationScanner, and persists nothing —
 * finalize() returns proposed changes the member reviews and applies to their program later.
 *
 * The existing program + the athlete profile flow through `$context` (never instance state) and
 * ground the request. "No changes recommended" is a legitimate review outcome, so an empty
 * adjustment set is a success (200, empty list) — not a failed attempt.
 */
class PlanAdjustmentGenerator extends AiGenerator
{
    public function __construct(LlmGateway $gateway, ContraindicationScanner $scanner)
    {
        parent::__construct($gateway, $scanner);
    }

    /**
     * @return Collection<int, array{exercise: Exercise, adjustment: array<string, mixed>}> safe proposals
     */
    public function generate(Person $person, Program $program, ?string $goal = null): Collection
    {
        return $this->runLoop($person, ['program' => $program, 'goal' => $goal]);
    }

    protected function feature(): string
    {
        return 'plan_adjustment';
    }

    protected function exhaustedMessage(): string
    {
        return 'Could not produce safe adjustments for this program. Please try again.';
    }

    protected function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['adjustments']) || ! is_array($decoded['adjustments'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every prescribed slug to a real Exercise. Returns null only if a slug was
     * hallucinated (found ≠ asked); an empty adjustment set resolves to an empty collection so it
     * passes the scan and finalizes to "no changes" (200). Only the prescribed `exercise_slug` is
     * resolved/scanned — `replaces_slug` (an exercise being removed) is never the safety hazard.
     *
     * @return Collection<string, Exercise>|null keyed by slug
     */
    protected function resolve(array $parsed, array $context): ?Collection
    {
        $slugs = collect($parsed['adjustments'])
            ->pluck('exercise_slug')
            ->filter()
            ->unique()
            ->values();

        $found = Exercise::whereIn('slug', $slugs)->get()->keyBy('slug');

        // ponytail: empty slugs → empty collection (no-changes), not null (failed attempt).
        return $found->count() === $slugs->count() ? $found : null;
    }

    /**
     * Pair each proposed change (in model order) with its resolved Exercise. No persistence —
     * these are proposals the member applies later.
     *
     * @param  Collection<string, Exercise>  $resolved
     * @return Collection<int, array{exercise: Exercise, adjustment: array<string, mixed>}>
     */
    protected function finalize(Person $person, array $parsed, Collection $resolved, array $context): Collection
    {
        return collect($parsed['adjustments'])
            ->filter(fn ($a) => isset($a['exercise_slug'], $resolved[$a['exercise_slug']]))
            ->map(fn ($a) => ['exercise' => $resolved[$a['exercise_slug']], 'adjustment' => $a])
            ->values();
    }

    /**
     * Ground the model on the athlete profile, the current program, and the real library, asking
     * for incremental, safe progression/deload adjustments. Untested by the fake gateway — kept
     * minimal and real for the Claude adapter (Q5); richer RAG on recent performance is deferred.
     *
     * @param  array{program: Program, goal: ?string}  $context
     * @param  list<string>  $avoid
     */
    protected function buildRequest(Person $person, array $context, array $avoid, string $tier): LlmRequest
    {
        $program = $context['program'];
        $profile = AiInputProfile::for($person);

        $library = Exercise::query()->orderBy('name')->limit(200)->get(['slug', 'name'])
            ->map(fn (Exercise $e) => $e->slug.' — '.$e->name)->implode("\n");

        $system = 'You are a certified strength & conditioning coach reviewing an athlete\'s current '
            .'training program. Propose incremental, safe adjustments (swaps, added/removed work, '
            .'progression or deload) that improve the program for this athlete, respecting their '
            .'equipment, experience, and injuries. Use ONLY exercises from the provided library, '
            .'referenced by their exact slug. If the program is already appropriate, return an empty '
            .'adjustments list. Respond with JSON only, matching the given schema — no prose.';

        $prompt = 'Athlete profile:\n'.json_encode($profile, JSON_PRETTY_PRINT)
            ."\n\nCurrent program:\n".$this->describeProgram($program)
            .($context['goal'] ? "\n\nAdjustment goal: ".$context['goal'] : '')
            ."\n\nExercise library (slug — name):\n".$library;

        if ($avoid !== []) {
            $prompt .= "\n\nA previous proposal was rejected by the safety review. Do NOT prescribe "
                .'these contraindicated exercises: '.implode(', ', $avoid).'.';
        }

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: $this->schema(),
            tier: $tier,
            feature: 'plan_adjustment',
            metadata: ['program' => $program->id, 'avoid' => $avoid],
        );
    }

    private function describeProgram(Program $program): string
    {
        $program->loadMissing('workouts.workoutExercises.exercise');

        return $program->workouts->map(function (Workout $w) {
            $exercises = $w->workoutExercises
                ->map(fn (WorkoutExercise $we) => '  - '.($we->exercise?->slug ?? '?')
                    .' ('.($we->target_sets ?? '?').'x'.($we->target_reps ?? '?').')')
                ->implode("\n");

            return 'Day '.$w->day_index.' — '.$w->name."\n".$exercises;
        })->implode("\n");
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['adjustments'],
            'properties' => [
                'adjustments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['exercise_slug', 'action'],
                        'properties' => [
                            'exercise_slug' => ['type' => 'string'],
                            'action' => ['type' => 'string', 'enum' => ['swap', 'add', 'remove', 'adjust']],
                            'replaces_slug' => ['type' => 'string'],
                            'target_sets' => ['type' => 'integer'],
                            'target_reps' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
