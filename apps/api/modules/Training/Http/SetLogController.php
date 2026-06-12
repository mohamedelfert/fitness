<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Models\Session;
use Modules\Training\Models\SetLog;

class SetLogController extends Controller
{
    public function store(Request $request, string $session): JsonResponse
    {
        $validated = $request->validate([
            'exercise_id' => ['required', 'string', 'exists:exercises,id'],
            'set_index' => ['required', 'integer', 'min:1'],
            'reps' => ['required', 'integer', 'min:0'],
            'load' => ['nullable', 'numeric', 'min:0'],
            'rpe' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'rir' => ['nullable', 'numeric', 'min:0'],
            'client_ulid' => ['nullable', 'string'],
        ]);

        // Tenant/ownership: a person may only log to their own session.
        $sessionModel = Session::where('person_id', $request->user()->id)->findOrFail($session);

        // Idempotent offline sync (ADR-005): a retried mutation with the same client_ulid
        // resolves to the original row instead of duplicating it.
        if (! empty($validated['client_ulid'])) {
            $existing = SetLog::where('person_id', $request->user()->id)
                ->where('client_ulid', $validated['client_ulid'])
                ->first();

            if ($existing) {
                return response()->json(['data' => $this->shape($existing)], 200);
            }
        }

        $setLog = SetLog::create([
            'session_id' => $sessionModel->id,
            'person_id' => $request->user()->id,
            'exercise_id' => $validated['exercise_id'],
            'set_index' => $validated['set_index'],
            'reps' => $validated['reps'],
            'load' => $validated['load'] ?? null,
            'rpe' => $validated['rpe'] ?? null,
            'rir' => $validated['rir'] ?? null,
            'client_ulid' => $validated['client_ulid'] ?? null,
            'logged_at' => now(),
        ]);

        return response()->json(['data' => $this->shape($setLog)], 201);
    }

    private function shape(SetLog $s): array
    {
        return [
            'id' => $s->id,
            'session_id' => $s->session_id,
            'exercise_id' => $s->exercise_id,
            'set_index' => $s->set_index,
            'reps' => $s->reps,
            'load' => $s->load,
            'rpe' => $s->rpe,
            'logged_at' => $s->logged_at?->toIso8601String(),
        ];
    }
}
