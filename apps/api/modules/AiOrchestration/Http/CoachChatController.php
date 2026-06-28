<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Models\CoachMessage;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\CoachChatService;

/**
 * Conversational AI coach (FR-AI-008) — `POST /v1/ai/coach/chat` to send a message,
 * `GET /v1/ai/coach/chat` for the transcript. Same preconditions as every AI feature: the
 * `ai-plan.generate` gate (403) + completed onboarding (422). Each message is charged one
 * AICredit (402 if the wallet can't cover it), debited only on a successful reply.
 */
class CoachChatController extends Controller
{
    public function store(Request $request, CoachChatService $coach, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages(['onboarding' => 'Complete onboarding before chatting with the coach.']);
        }

        // `string` rejects arrays; `max` caps both the TEXT column (no "Data too long" 500) and the
        // prompt cost (NFR-AI-001). `required` passes a whitespace-only string, so trim-check after.
        $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message = trim($request->input('message'));
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'A message is required.']);
        }

        $cost = $meter->costFor('coach_chat');
        $meter->ensureCanAfford($person, $cost);

        $result = $coach->chat($person, $message);

        $meter->debit($person, $cost, 'coach_chat', $result['message']);

        return response()->json(['data' => [
            'reply' => $result['message']->content,
            'interaction_id' => $result['interaction_id'],
        ]]);
    }

    public function history(Request $request): JsonResponse
    {
        $person = $request->user();

        // ponytail: returns the whole transcript; add cursor pagination if a thread ever grows large.
        $messages = CoachMessage::where('person_id', $person->id)
            ->orderBy('id')
            ->get()
            ->map(fn (CoachMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
                'at' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $messages]);
    }
}
