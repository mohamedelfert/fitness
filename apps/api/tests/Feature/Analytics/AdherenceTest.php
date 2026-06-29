<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodLog;
use Modules\Training\Models\Program;
use Modules\Training\Models\Session;
use Modules\Training\Models\Workout;
use Tests\TestCase;

/**
 * Adherence analytics (FR-AN-002) — `GET /v1/me/adherence`. Activity over a 28-day window plus,
 * when the Person has an active program, an adherence_pct against its planned weekly cadence
 * (= the program's workout count). No active program → planned/pct null (no fabricated
 * denominator). Computed on read, person-scoped.
 */
class AdherenceTest extends TestCase
{
    use RefreshDatabase;

    private function logSession(Person $person, int $daysAgo): void
    {
        Session::create(['person_id' => $person->id, 'started_at' => now()->subDays($daysAgo)->setTime(12, 0)]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/me/adherence')->assertUnauthorized();
    }

    public function test_counts_activity_within_window_only(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->logSession($person, 0);
        $this->logSession($person, 3);
        $this->logSession($person, 3);   // 2 sessions, same day → 1 active day
        $this->logSession($person, 40);  // outside 28d window → ignored
        $this->logSession($other, 1);    // another person → must not leak
        FoodLog::create(['person_id' => $person->id, 'meal_type' => 'lunch', 'kcal' => 500, 'logged_at' => now()]);

        $this->getJson('/v1/me/adherence')->assertOk()
            ->assertJsonPath('data.sessions', 3)
            ->assertJsonPath('data.active_days', 2)
            ->assertJsonPath('data.nutrition_logged_days', 1)
            ->assertJsonPath('data.planned_per_week', null)
            ->assertJsonPath('data.adherence_pct', null);
    }

    public function test_adherence_pct_against_active_program_cadence(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $program = Program::factory()->create(['person_id' => $person->id, 'status' => 'active']);
        Workout::factory()->count(4)->create(['program_id' => $program->id]); // planned 4/wk

        // 8 sessions over the 4-week window vs planned 16 → 50%.
        foreach ([0, 2, 4, 6, 8, 10, 12, 14] as $d) {
            $this->logSession($person, $d);
        }

        $data = $this->getJson('/v1/me/adherence')->assertOk()
            ->assertJsonPath('data.planned_per_week', 4)
            ->assertJsonPath('data.program_id', $program->id)
            ->json('data');
        $this->assertEquals(50.0, $data['adherence_pct']); // loose: whole-number float serialises as JSON int
    }

    public function test_current_streak_counts_consecutive_days_to_today(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->logSession($person, 0);
        $this->logSession($person, 1);
        $this->logSession($person, 2);
        // gap at day 3
        $this->logSession($person, 4);

        $this->getJson('/v1/me/adherence')->assertOk()
            ->assertJsonPath('data.current_streak_days', 3);
    }

    public function test_streak_survives_a_yet_to_train_today_with_yesterday_active(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        // No session today, but yesterday + day before → streak alive at 2 (today isn't over).
        $this->logSession($person, 1);
        $this->logSession($person, 2);

        $this->getJson('/v1/me/adherence')->assertOk()
            ->assertJsonPath('data.current_streak_days', 2);
    }

    public function test_streak_is_zero_when_last_session_is_older_than_yesterday(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->logSession($person, 3);
        $this->logSession($person, 4);

        $this->getJson('/v1/me/adherence')->assertOk()
            ->assertJsonPath('data.current_streak_days', 0);
    }
}
