<?php

namespace Tests\Unit;

use App\Support\DayStreak;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shared day-streak calculation (used by adherence, habits and gamification): consecutive
 * days present in a set of date strings, counting back from today, with one day of grace so a
 * not-yet-active today doesn't break a streak that ran through yesterday.
 */
class DayStreakTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_counts_consecutive_days_to_today(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');
        $this->assertSame(3, DayStreak::current(['2026-06-29', '2026-06-28', '2026-06-27']));
    }

    public function test_grace_for_a_not_yet_active_today(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');
        // No 06-29, but yesterday + before → streak alive at 2 (today isn't over).
        $this->assertSame(2, DayStreak::current(['2026-06-28', '2026-06-27']));
    }

    public function test_broken_when_latest_is_older_than_yesterday(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');
        $this->assertSame(0, DayStreak::current(['2026-06-26']));
    }

    public function test_dedupes_multiple_logs_on_the_same_day(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');
        $this->assertSame(2, DayStreak::current(['2026-06-29', '2026-06-29', '2026-06-28']));
    }

    public function test_empty_is_zero(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');
        $this->assertSame(0, DayStreak::current([]));
    }
}
