<?php

namespace Modules\Engagement\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Engagement\Services\GamificationCalculator;

/** Gamification (FR-ENG-003) — `GET /v1/me/gamification`. XP/level/streak, person-scoped, on read. */
class GamificationController extends Controller
{
    public function show(Request $request, GamificationCalculator $calculator): JsonResponse
    {
        return response()->json(['data' => $calculator->for($request->user())]);
    }
}
