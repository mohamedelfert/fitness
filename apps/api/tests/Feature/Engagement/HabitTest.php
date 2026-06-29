<?php

namespace Tests\Feature\Engagement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Habit tracking (FR-ENG-002) — `habits` (parent) + append-only idempotent `habit_logs`
 * (offline sync, ADR-005), person-scoped. Per-habit current_streak (consecutive days with a
 * log, one day of grace) mirrors the adherence streak math. The behavioural *nudge* beyond raw
 * streaks is deferred (an advisory-AI surface).
 */
class HabitTest extends TestCase
{
    use RefreshDatabase;

    private function logOn(Habit $habit, int $daysAgo): void
    {
        HabitLog::create([
            'habit_id' => $habit->id,
            'person_id' => $habit->person_id,
            'logged_at' => now()->subDays($daysAgo)->setTime(12, 0),
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/v1/habits', [])->assertUnauthorized();
    }

    public function test_creates_a_habit(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/habits', ['name' => 'Drink water', 'cadence' => 'daily', 'target_per_period' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Drink water')
            ->assertJsonPath('data.cadence', 'daily')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('habits', ['person_id' => $person->id, 'name' => 'Drink water']);
    }

    public function test_validates_cadence(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/habits', ['name' => 'x', 'cadence' => 'hourly'])->assertStatus(422);
    }

    public function test_lists_own_habits_with_current_streak(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($person);
        $habit = Habit::factory()->for($person)->create(['name' => 'Stretch']);
        Habit::factory()->for($other)->create(); // another person's habit must not leak
        $this->logOn($habit, 0);
        $this->logOn($habit, 1); // today + yesterday → streak 2

        $this->getJson('/v1/habits')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Stretch')
            ->assertJsonPath('data.0.current_streak', 2);
    }

    public function test_logs_a_habit_completion(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $habit = Habit::factory()->for($person)->create();

        $this->postJson('/v1/habit-logs', ['habit_id' => $habit->id])->assertCreated();

        $this->assertDatabaseHas('habit_logs', ['habit_id' => $habit->id, 'person_id' => $person->id]);
    }

    public function test_habit_log_is_idempotent_on_client_ulid(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $habit = Habit::factory()->for($person)->create();
        $payload = ['habit_id' => $habit->id, 'client_ulid' => '01HZZZZZZZZZZZZZZZZZZZZZZZ'];

        $this->postJson('/v1/habit-logs', $payload)->assertCreated();
        $this->postJson('/v1/habit-logs', $payload)->assertOk(); // replay → 200, no duplicate

        $this->assertSame(1, HabitLog::where('habit_id', $habit->id)->count());
    }

    public function test_cannot_log_another_persons_habit(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($person);
        $habit = Habit::factory()->for($other)->create();

        $this->postJson('/v1/habit-logs', ['habit_id' => $habit->id])->assertNotFound();
    }
}
