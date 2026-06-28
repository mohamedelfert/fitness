<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Models\DailyRecommendation;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\DailyRecommendationGenerator;

/**
 * AI daily recommendation endpoint (FR-AI-004) — `GET /v1/ai/recommendations/today`. Same
 * preconditions as the other AI features (the `ai-plan.generate` gate → 403, completed
 * onboarding → 422). Materialised once per day: a cache hit returns the stored line for free;
 * a miss runs the generator, which is charged one AICredit (402 if the wallet can't cover it)
 * and debited only on success. Advisory-only — the contraindication sandwich does not apply.
 */
class DailyRecommendationController extends Controller
{
    public function today(Request $request, DailyRecommendationGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete onboarding before requesting recommendations.',
            ]);
        }

        $date = now()->toDateString();

        $existing = DailyRecommendation::where('person_id', $person->id)->where('rec_date', $date)->first();
        if ($existing !== null) {
            return $this->present($existing);
        }

        $cost = $meter->costFor('daily_recommendation');
        $meter->ensureCanAfford($person, $cost);

        $recommendation = $generator->generate($person, $date);

        $meter->debit($person, $cost, 'daily_recommendation', $recommendation);

        return $this->present($recommendation);
    }

    private function present(DailyRecommendation $recommendation): JsonResponse
    {
        return response()->json(['data' => [
            'date' => $recommendation->rec_date->toDateString(),
            'message' => $recommendation->message,
        ]]);
    }
}
