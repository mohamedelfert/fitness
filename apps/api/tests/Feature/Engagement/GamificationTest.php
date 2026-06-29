<?php

namespace Tests\Feature\Engagement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Session;
use Tests\TestCase;

/**
 * Gamification (FR-ENG-003) — `GET /v1/me/gamification`. XP / level / streak computed on read
 * from the Person's append-only activity (sessions + habit logs only). On-read is correct here
 * *because* those source logs are append-only (INV-002), so total XP is deterministic and
 * monotonic — it can't decrease. Badges are deferred (a historical award → persisted, not
 * recomputed). Person-scoped.
 */
class GamificationTest extends TestCase
{
    use RefreshDatabase;

    private function sessionOn(Person $person, int $daysAgo): void
    {
        Session::create(['person_id' => $person->id, 'started_at' => now()->subDays($daysAgo)->setTime(12, 0)]);
    }

    private function habitLog(Person $person, Habit $habit, int $daysAgo): void
    {
        HabitLog::create(['habit_id' => $habit->id, 'person_id' => $person->id, 'logged_at' => now()->subDays($daysAgo)]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/me/gamification')->assertUnauthorized();
    }

    public function test_computes_xp_level_and_streak_from_activity(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->sessionOn($person, 0);
        $this->sessionOn($person, 1);
        $this->sessionOn($person, 2); // 3 sessions, 3-day streak
        $habit = Habit::factory()->for($person)->create();
        $this->habitLog($person, $habit, 0);
        $this->habitLog($person, $habit, 1); // 2 habit logs

        $xp = 3 * (int) config('gamification.points.session') + 2 * (int) config('gamification.points.habit_log');
        $per = (int) config('gamification.xp_per_level');

        $this->getJson('/v1/me/gamification')->assertOk()
            ->assertJsonPath('data.xp', $xp)
            ->assertJsonPath('data.level', intdiv($xp, $per) + 1)
            ->assertJsonPath('data.xp_into_level', $xp % $per)
            ->assertJsonPath('data.xp_for_next_level', $per)
            ->assertJsonPath('data.streak_days', 3);
    }

    public function test_empty_state_is_level_one(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/gamification')->assertOk()
            ->assertJsonPath('data.xp', 0)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.streak_days', 0);
    }

    public function test_is_person_scoped(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->sessionOn($other, 0); // another person's activity must not count
        $habit = Habit::factory()->for($other)->create();
        $this->habitLog($other, $habit, 0);

        $this->getJson('/v1/me/gamification')->assertOk()
            ->assertJsonPath('data.xp', 0)
            ->assertJsonPath('data.level', 1);
    }
}
