<?php

namespace Modules\Engagement\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Engagement\Services\BadgeAwarder;
use Modules\Engagement\Services\GamificationCalculator;

/**
 * Gamification (FR-ENG-003) — `GET /v1/me/gamification`. XP/level/streak computed on read, plus
 * earned badges (awarded + persisted on first threshold-cross). Person-scoped.
 */
class GamificationController extends Controller
{
    public function show(Request $request, GamificationCalculator $calculator, BadgeAwarder $awarder): JsonResponse
    {
        $person = $request->user();
        $stats = $calculator->for($person);

        return response()->json(['data' => [...$stats, 'badges' => $awarder->sync($person, $stats)]]);
    }
}
