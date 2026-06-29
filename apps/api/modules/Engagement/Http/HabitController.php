<?php

namespace Modules\Engagement\Http;

use App\Http\Controllers\Controller;
use App\Support\DayStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;

/** Habit tracking (FR-ENG-002) — create/list a Person's habits, each with its current streak. */
class HabitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $habits = Habit::where('person_id', $request->user()->id)
            ->latest()->get()
            ->map(fn (Habit $h) => $this->shape($h));

        return response()->json(['data' => $habits]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cadence' => ['required', Rule::in(Habit::CADENCES)],
            'target_per_period' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $habit = Habit::create([
            'person_id' => $request->user()->id,
            'name' => $validated['name'],
            'cadence' => $validated['cadence'],
            'target_per_period' => $validated['target_per_period'] ?? 1,
            'active' => true,
        ]);

        return response()->json(['data' => $this->shape($habit)], 201);
    }

    /** @return array<string, mixed> */
    private function shape(Habit $habit): array
    {
        return [
            'id' => $habit->id,
            'name' => $habit->name,
            'cadence' => $habit->cadence,
            'target_per_period' => $habit->target_per_period,
            'active' => $habit->active,
            'current_streak' => $this->currentStreak($habit),
        ];
    }

    /** Consecutive days with a completion (one day of grace) — shared day-streak math. */
    private function currentStreak(Habit $habit): int
    {
        $days = HabitLog::where('habit_id', $habit->id)
            ->where('logged_at', '>=', now()->subDays(180))
            ->get(['logged_at'])
            ->map(fn ($l) => $l->logged_at->toDateString());

        return DayStreak::current($days);
    }
}
