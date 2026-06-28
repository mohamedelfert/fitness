<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\CoachMessage;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;

/**
 * Conversational AI coach (FR-AI-008). Like the daily recommendation this is advisory-only and
 * deliberately NOT an AiGenerator subclass: it prescribes no library entities, so the
 * resolve→contraindication-scan sandwich has nothing to act on. Safety is by construction — the
 * system prompt forbids specific exercise/load/supplement/medical prescriptions; structured
 * plans come from the safety-gated generators.
 *
 * One implicit thread per person (coach_messages). Each call replays the recent transcript into
 * the prompt for multi-turn context (capped by config for cost — NFR-AI-001), then appends the
 * new turn. Persists nothing and is not charged unless the model returns a usable reply.
 */
class CoachChatService
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly AiInteractionLogger $logger,
    ) {}

    /**
     * Generate the coach's reply to `$message`, persisting both turns on success.
     *
     * @return array{message: CoachMessage, interaction_id: string}
     *
     * @throws ValidationException 422 when the model returns nothing usable (never a 500)
     */
    public function chat(Person $person, string $message): array
    {
        $tier = (string) config('ai.coach_chat.tier', 'default');
        $request = $this->buildRequest($person, $message, $tier);

        $startedAt = microtime(true);
        $result = $this->gateway->complete($request);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $reply = trim($result->text);

        // Log the call (audit/cost) BEFORE persisting/charging, so the trail survives a 422.
        $interaction = $this->logger->log($person, 'coach_chat', $result, $reply === '' ? 'error' : 'passed', $latencyMs, $tier);

        if ($reply === '') {
            throw ValidationException::withMessages(['coach_chat' => 'Could not produce a reply. Please try again.']);
        }

        $assistant = DB::transaction(function () use ($person, $message, $reply) {
            CoachMessage::create(['person_id' => $person->id, 'role' => 'user', 'content' => $message]);

            return CoachMessage::create(['person_id' => $person->id, 'role' => 'assistant', 'content' => $reply]);
        });

        return ['message' => $assistant, 'interaction_id' => $interaction->id];
    }

    private function buildRequest(Person $person, string $message, string $tier): LlmRequest
    {
        $system = 'You are a supportive, encouraging fitness coach in conversation with an athlete. '
            .'Answer their questions and motivate them, grounded in the profile and goals below. Keep '
            .'replies concise and conversational. Do NOT prescribe specific exercises, loads, sets/reps, '
            .'supplements, or medications, and do NOT give medical advice — structured training and '
            .'nutrition plans come from the dedicated planners, and medical concerns should be referred '
            .'to a qualified professional.';

        $profile = AiInputProfile::for($person);
        $prompt = 'Athlete profile:'."\n".json_encode($profile, JSON_PRETTY_PRINT);

        $history = $this->recentHistory($person);
        if ($history !== '') {
            $prompt .= "\n\nConversation so far:\n".$history;
        }

        $prompt .= "\n\nUser: ".$message;

        return new LlmRequest(system: $system, prompt: $prompt, tier: $tier, feature: 'coach_chat');
    }

    /** The last N turns in chronological order, formatted for the prompt (multi-turn context). */
    private function recentHistory(Person $person): string
    {
        $limit = (int) config('ai.coach_chat.history_limit', 10);

        return CoachMessage::where('person_id', $person->id)
            ->orderByDesc('id')   // ULIDs are monotonic → id order == creation order, tie-free
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (CoachMessage $m) => ucfirst($m->role).': '.$m->content)
            ->implode("\n");
    }
}
