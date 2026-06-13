<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Jobs\DetectPersonalRecords;
use Modules\Training\Models\Session;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $session = Session::create([
            'person_id' => $request->user()->id,
            'workout_id' => $request->input('workout_id'),
            'started_at' => $request->input('started_at', now()),
            'source' => 'manual',
        ]);

        return response()->json(['data' => $this->shape($session)], 201);
    }

    /** POST /v1/sessions/{id}/finish — close the session and refresh PRs async (FR-TRN-004). */
    public function finish(Request $request, string $session): JsonResponse
    {
        // Owner-scoped: a non-owner sees 404, never 403 (existence hidden, INV-001 spirit).
        $model = Session::where('person_id', $request->user()->id)->findOrFail($session);
        $model->forceFill(['ended_at' => now()])->save();

        DetectPersonalRecords::dispatch($model->id);

        return response()->json(['data' => $this->shape($model)]);
    }

    /** @return array<string, mixed> */
    private function shape(Session $session): array
    {
        return [
            'id' => $session->id,
            'person_id' => $session->person_id,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
        ];
    }
}
