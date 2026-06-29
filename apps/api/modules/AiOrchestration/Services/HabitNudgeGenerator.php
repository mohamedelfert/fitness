<?php

namespace Modules\AiOrchestration\Services;

use App\Support\DayStreak;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;

/**
 * AI habit nudge (FR-ENG-002 — "behavioural nudges beyond raw streaks"). Advisory contextual
 * encouragement grounded in the Person's active habits + each habit's streak and whether it's been
 * logged today. Like recovery/daily-rec it prescribes no library entities, so it is NOT an
 * AiGenerator subclass and runs no contraindication sandwich — safety is by construction (the
 * prompt forbids specific exercise/medical prescriptions). Fresh each call; cost bounded by
 * metering, not caching (habit state changes intraday).
 */
class HabitNudgeGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly AiInteractionLogger $logger,
    ) {}

    /**
     * @return array{message: string, interaction_id: string}
     *
     * @throws ValidationException 422 when the model returns nothing usable (never a 500)
     */
    public function generate(Person $person): array
    {
        $tier = (string) config('ai.habit_nudge.tier', 'cheap');
        $request = $this->buildRequest($person, $tier);

        $startedAt = microtime(true);
        $result = $this->gateway->complete($request);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $message = $this->extractMessage($result->text);
        $interaction = $this->logger->log($person, 'habit_nudge', $result, $message === '' ? 'error' : 'passed', $latencyMs, $tier);

        if ($message === '') {
            throw ValidationException::withMessages(['habit_nudge' => 'Could not produce a habit nudge. Please try again.']);
        }

        return ['message' => $message, 'interaction_id' => $interaction->id];
    }

    /** Accept either structured `{ "message": "…" }` or a bare line. */
    private function extractMessage(string $text): string
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return trim($decoded['message']);
        }

        return trim($text);
    }

    private function buildRequest(Person $person, string $tier): LlmRequest
    {
        $system = 'You are an encouraging habit coach. From the athlete profile and their habits '
            .'(with current streaks and whether each was logged today), write ONE short (1-2 '
            .'sentence) behavioural nudge: celebrate a streak, gently flag a habit not yet done '
            .'today, or suggest the easiest next step. Do NOT prescribe specific exercises, loads, '
            .'medications or medical advice. Respond with JSON: {"message": "…"}.';

        $prompt = 'Athlete profile:'."\n".json_encode(AiInputProfile::for($person), JSON_PRETTY_PRINT)
            ."\n\nHabits: ".$this->habitSummary($person);

        return new LlmRequest(system: $system, prompt: $prompt, tier: $tier, feature: 'habit_nudge');
    }

    /** Active habits with each one's current day-streak and whether it's been logged today. */
    private function habitSummary(Person $person): string
    {
        $habits = Habit::where('person_id', $person->id)->where('active', true)->get();

        if ($habits->isEmpty()) {
            return 'no active habits';
        }

        $today = now()->toDateString();

        return $habits->map(function (Habit $habit) use ($today) {
            $days = HabitLog::where('habit_id', $habit->id)
                ->where('logged_at', '>=', now()->subDays(180))
                ->get(['logged_at'])
                ->map(fn ($l) => $l->logged_at->toDateString());

            return sprintf(
                '%s (cadence=%s, streak=%dd, logged_today=%s)',
                $habit->name, $habit->cadence, DayStreak::current($days), $days->contains($today) ? 'yes' : 'no',
            );
        })->implode('; ');
    }
}
