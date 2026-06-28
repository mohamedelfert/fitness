<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\RecoveryTipGenerator;

/**
 * AI recovery tips (FR-AI-005) — `GET /v1/ai/recovery`. Same preconditions as the other AI
 * features: the `ai-plan.generate` gate (403) + completed onboarding (422). Generated fresh each
 * call (reflects live wearable data), charged one AICredit per call (402 if unfunded), debited
 * only on a successful generation. Advisory-only — no contraindication sandwich applies.
 */
class RecoveryController extends Controller
{
    public function show(Request $request, RecoveryTipGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages(['onboarding' => 'Complete onboarding before requesting recovery advice.']);
        }

        $validated = $request->validate([
            'soreness' => ['sometimes', 'nullable', Rule::in(['none', 'mild', 'moderate', 'severe'])],
        ]);

        $cost = $meter->costFor('recovery');
        $meter->ensureCanAfford($person, $cost);

        $result = $generator->generate($person, $validated['soreness'] ?? null);

        $meter->debit($person, $cost, 'recovery');

        return response()->json(['data' => [
            'message' => $result['message'],
            'interaction_id' => $result['interaction_id'],
        ]]);
    }
}
