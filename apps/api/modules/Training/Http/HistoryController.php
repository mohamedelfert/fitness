<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Models\Session;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = Session::with('setLogs')
            ->where('person_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (Session $s) => [
                'id' => $s->id,
                'started_at' => $s->started_at?->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'set_logs' => $s->setLogs->map(fn ($sl) => [
                    'id' => $sl->id,
                    'exercise_id' => $sl->exercise_id,
                    'set_index' => $sl->set_index,
                    'reps' => $sl->reps,
                    'load' => $sl->load,
                    'rpe' => $sl->rpe,
                    'logged_at' => $sl->logged_at?->toIso8601String(),
                ])->all(),
            ]);

        return response()->json(['data' => $sessions]);
    }
}
