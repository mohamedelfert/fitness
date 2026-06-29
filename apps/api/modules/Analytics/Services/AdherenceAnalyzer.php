<?php

namespace Modules\Analytics\Services;

use App\Support\DayStreak;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodLog;
use Modules\Training\Models\Program;
use Modules\Training\Models\Session;
use Modules\Training\Models\Workout;

/**
 * Adherence analytics (FR-AN-002). Training + nutrition-logging activity over a 28-day window and,
 * when the Person has an active program, an adherence_pct against its planned weekly cadence
 * (= the program's workout count). No active program → planned/pct null — we never invent a
 * denominator. Computed on read (cheap person-scoped queries), person-scoped throughout.
 */
class AdherenceAnalyzer
{
    private const WINDOW_DAYS = 28;

    /** Streak lookback cap — a daily-training streak longer than this is exceptional. */
    private const STREAK_LOOKBACK_DAYS = 180;

    /** @return array<string, mixed> */
    public function for(Person $person): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $sessionDays = Session::where('person_id', $person->id)
            ->where('started_at', '>=', $since)
            ->get(['started_at'])
            ->map(fn ($s) => $s->started_at->toDateString());

        $sessions = $sessionDays->count();
        $weeks = self::WINDOW_DAYS / 7;

        $nutritionDays = (int) FoodLog::where('person_id', $person->id)
            ->where('logged_at', '>=', $since)
            ->selectRaw('COUNT(DISTINCT DATE(logged_at)) c')->value('c');

        $program = Program::where('person_id', $person->id)->where('status', 'active')
            ->orderByDesc('created_at')->first();
        // ponytail: planned = workout count assumes a weekly cycle; refine from mesocycle_json if
        // multi-week periodisation lands.
        $plannedPerWeek = $program ? Workout::where('program_id', $program->id)->count() : null;
        $adherencePct = $plannedPerWeek > 0
            ? round($sessions / ($plannedPerWeek * $weeks) * 100, 1)
            : null;

        return [
            'window_days' => self::WINDOW_DAYS,
            'sessions' => $sessions,
            'active_days' => $sessionDays->unique()->count(),
            'sessions_per_week' => round($sessions / $weeks, 2),
            'nutrition_logged_days' => $nutritionDays,
            'current_streak_days' => $this->currentStreak($person),
            'program_id' => $program?->id,
            'planned_per_week' => $plannedPerWeek,
            'adherence_pct' => $adherencePct,
        ];
    }

    /** Consecutive days with a session, counting back from today; one day of grace for today. */
    private function currentStreak(Person $person): int
    {
        $days = Session::where('person_id', $person->id)
            ->where('started_at', '>=', now()->subDays(self::STREAK_LOOKBACK_DAYS))
            ->get(['started_at'])
            ->map(fn ($s) => $s->started_at->toDateString());

        return DayStreak::current($days);
    }
}
