<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\HabitNudgeGenerator;

/**
 * AI habit nudge (FR-ENG-002) — `GET /v1/ai/habit-nudge`. Same preconditions as the other AI
 * features (the `ai-plan.generate` gate → 403, completed onboarding → 422). Generated fresh each
 * call (reflects live habit state), charged one AICredit (402 if the wallet can't cover it),
 * debited only on success. Advisory-only — no contraindication sandwich applies.
 */
class HabitNudgeController extends Controller
{
    public function show(Request $request, HabitNudgeGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages(['onboarding' => 'Complete onboarding before requesting a habit nudge.']);
        }

        $cost = $meter->costFor('habit_nudge');
        $meter->ensureCanAfford($person, $cost);

        $result = $generator->generate($person);

        $meter->debit($person, $cost, 'habit_nudge');

        return response()->json(['data' => [
            'message' => $result['message'],
            'interaction_id' => $result['interaction_id'],
        ]]);
    }
}
