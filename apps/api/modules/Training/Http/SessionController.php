<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => [
            'id' => $session->id,
            'person_id' => $session->person_id,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
        ]], 201);
    }
}
