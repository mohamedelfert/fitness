<?php

namespace Modules\Engagement\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Engagement\Models\Goal;

class GoalController extends Controller
{
    /** GET /v1/goals — the authenticated Person's goals (FR-ENG-001). */
    public function index(Request $request): JsonResponse
    {
        $goals = Goal::where('person_id', $request->user()->id)
            ->latest()->get()
            ->map(fn (Goal $g) => $this->shape($g));

        return response()->json(['data' => $goals]);
    }

    /** POST /v1/goals — create a goal for the authenticated Person. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(Goal::TYPES)],
            'target_value' => ['nullable', 'numeric'],
            'target_unit' => ['nullable', 'string', 'max:16'],
            'target_date' => ['nullable', 'date'],
        ]);

        $goal = Goal::create([
            'person_id' => $request->user()->id,
            'status' => 'active',
            ...$validated,
        ]);

        return response()->json(['data' => $this->shape($goal)], 201);
    }

    private function shape(Goal $g): array
    {
        return [
            'id' => $g->id,
            'type' => $g->type,
            'target_value' => $g->target_value,
            'target_unit' => $g->target_unit,
            'target_date' => $g->target_date?->toDateString(),
            'status' => $g->status,
        ];
    }
}
