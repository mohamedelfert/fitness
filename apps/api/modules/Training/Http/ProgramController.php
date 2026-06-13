<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;

/**
 * Program read model (FR-TRN-005, FR-AI-001). Person-scoped: cross-person access is
 * hidden as 404, never 403 (API_SPECIFICATION §13). Generation/authoring is AI (E1.6)
 * or coach (P2); this is the read surface the Today loop and app consume.
 */
class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $programs = Program::where('person_id', $request->user()->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Program $p) => $this->summary($p));

        return response()->json(['data' => $programs]);
    }

    public function show(Request $request, string $program): JsonResponse
    {
        // Scoped lookup → 404 (not 403) when the program isn't this Person's.
        $model = Program::where('person_id', $request->user()->id)
            ->with(['workouts.workoutExercises.exercise'])
            ->findOrFail($program);

        return response()->json(['data' => [
            ...$this->summary($model),
            'mesocycle' => $model->mesocycle_json,
            'workouts' => $model->workouts->map(fn (Workout $w) => [
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
                    'target_load' => $we->target_load,
                    'rest_sec' => $we->rest_sec,
                    'tempo' => $we->tempo,
                    'notes' => $we->notes,
                ])->all(),
            ])->all(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(Program $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'source' => $p->source,
            'status' => $p->status,
            'start_date' => $p->start_date?->toDateString(),
        ];
    }
}
