<?php

namespace Modules\Engagement\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;

/**
 * Habit completion logging (FR-ENG-002). Append-only & idempotent on client_ulid (offline sync,
 * ADR-005). The habit is person-owned, so an unknown/cross-person habit_id is a 404.
 */
class HabitLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'habit_id' => ['required', 'string'],
            'client_ulid' => ['nullable', 'string'],
            'logged_at' => ['nullable', 'date'],
        ]);

        $person = $request->user();
        $habit = Habit::where('person_id', $person->id)->find($validated['habit_id']);
        abort_if($habit === null, 404);

        if (! empty($validated['client_ulid'])) {
            $existing = HabitLog::where('person_id', $person->id)
                ->where('client_ulid', $validated['client_ulid'])
                ->first();

            if ($existing) {
                return response()->json(['data' => $this->shape($existing)], 200);
            }
        }

        $log = HabitLog::create([
            'habit_id' => $habit->id,
            'person_id' => $person->id,
            'client_ulid' => $validated['client_ulid'] ?? null,
            'logged_at' => $validated['logged_at'] ?? now(),
        ]);

        return response()->json(['data' => $this->shape($log)], 201);
    }

    /** @return array<string, mixed> */
    private function shape(HabitLog $log): array
    {
        return [
            'id' => $log->id,
            'habit_id' => $log->habit_id,
            'logged_at' => $log->logged_at?->toIso8601String(),
        ];
    }
}
