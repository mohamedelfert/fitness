<?php

namespace Modules\Engagement\Services;

use App\Support\DayStreak;
use Modules\Engagement\Models\HabitLog;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Session;

/**
 * Gamification (FR-ENG-003) — XP / level / streak for a Person, computed on read from their
 * append-only activity (sessions + habit logs). On-read is correct *because* those source logs
 * are append-only (INV-002): total XP is a deterministic, monotonic function of countable rows.
 *
 * ponytail: derive-on-read, source set kept to 2. Flip to the DATABASE_DESIGN `xp_ledger`
 * (append-only XP events emitted by per-module listeners) when ANY of: XP becomes spendable (then
 * it needs the AICredit signed-ledger pattern), notifications need award-timestamps ("you earned
 * 50 XP today"), or the source set outgrows ~3 and the cross-module coupling hurts.
 */
class GamificationCalculator
{
    /** @return array<string, mixed> */
    public function for(Person $person): array
    {
        $xp = Session::where('person_id', $person->id)->count() * (int) config('gamification.points.session')
            + HabitLog::where('person_id', $person->id)->count() * (int) config('gamification.points.habit_log');

        $per = max(1, (int) config('gamification.xp_per_level'));

        $streakDays = DayStreak::current(
            Session::where('person_id', $person->id)
                ->where('started_at', '>=', now()->subDays(180))
                ->get(['started_at'])
                ->map(fn ($s) => $s->started_at->toDateString())
        );

        return [
            'xp' => $xp,
            'level' => intdiv($xp, $per) + 1,
            'xp_into_level' => $xp % $per,
            'xp_for_next_level' => $per,
            'streak_days' => $streakDays,
        ];
    }
}
