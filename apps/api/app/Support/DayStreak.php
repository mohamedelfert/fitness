<?php

namespace App\Support;

/**
 * The shared day-streak calculation for adherence, habits and gamification (FR-AN-002 /
 * FR-ENG-002 / FR-ENG-003). Given the set of days an activity happened on, returns how many
 * consecutive days run back from today — with one day of grace so a not-yet-active today does
 * not break a streak that ran through yesterday.
 *
 * ponytail: day-granular only. Cadence-aware streaks (weekly target periods) would need a
 * different unit and are deferred until a habit cadence actually requires them.
 */
class DayStreak
{
    /** @param  iterable<string>  $dateStrings  'Y-m-d' days the activity occurred on (dupes ok). */
    public static function current(iterable $dateStrings): int
    {
        $days = collect($dateStrings)->unique()->flip();

        $cursor = now()->startOfDay();
        if (! $days->has($cursor->toDateString()) && $days->has($cursor->copy()->subDay()->toDateString())) {
            $cursor->subDay();
        }

        $streak = 0;
        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
