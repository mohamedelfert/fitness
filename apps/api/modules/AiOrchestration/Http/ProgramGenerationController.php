<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\ProgramGenerator;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;

/**
 * AI program generation endpoint (FR-AI-001). Enforces the two preconditions before the
 * Brain is ever called:
 *   1. the `ai-plan.generate` Gate — only a PAR-Q+-cleared Person (403 otherwise); the
 *      release-blocking safety boundary (FR-AI-007 / INV-005).
 *   2. onboarding completeness — the profile the Brain needs must exist (422 otherwise).
 * Generation + the safety post-eval live in ProgramGenerator.
 */
class ProgramGenerationController extends Controller
{
    public function store(Request $request, ProgramGenerator $generator): JsonResponse
    {
        $person = $request->user();

        // Safety gate first: an unscreened Person is forbidden, regardless of onboarding.
        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete onboarding before generating a program.',
            ]);
        }

        $program = $generator->generate($person);

        return response()->json(['data' => $this->present($program)], 201);
    }

    /** @return array<string, mixed> */
    private function present(Program $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'source' => $program->source,
            'status' => $program->status,
            'start_date' => $program->start_date?->toDateString(),
            'workouts' => $program->workouts->map(fn (Workout $w) => [
                'id' => $w->id,
                'day_index' => $w->day_index,
                'name' => $w->name,
                'ordering' => $w->ordering,
                'exercises' => $w->workoutExercises->map(fn (WorkoutExercise $we) => [
                    'id' => $we->id,
                    'exercise_id' => $we->exercise_id,
                    'exercise_name' => $we->exercise?->name,
                    'order' => $we->order,
                    'target_sets' => $we->target_sets,
                    'target_reps' => $we->target_reps,
                    'rest_sec' => $we->rest_sec,
                ])->all(),
            ])->all(),
        ];
    }
}
