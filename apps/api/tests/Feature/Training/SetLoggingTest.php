<?php

namespace Tests\Feature\Training;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Tests\TestCase;

/**
 * The Phase 0 walking-skeleton vertical slice (EXECUTION_PLAN.md §3):
 * log a set -> sync -> see it. Proves the append-only + idempotent-sync
 * contract (ADR-005) end-to-end: DB -> API -> response.
 */
class SetLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_can_log_a_set_and_see_it_in_history(): void
    {
        $person = Person::factory()->create();
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs($person);

        $session = $this->postJson('/v1/sessions', [])
            ->assertCreated()
            ->json('data');

        $this->postJson("/v1/sessions/{$session['id']}/sets", [
            'exercise_id' => $exercise->id,
            'set_index' => 1,
            'reps' => 10,
            'load' => 60.0,
            'rpe' => 8,
        ])->assertCreated()
            ->assertJsonPath('data.reps', 10);

        $history = $this->getJson('/v1/me/history')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $history);
        $this->assertCount(1, $history[0]['set_logs']);
        $this->assertEquals(10, $history[0]['set_logs'][0]['reps']);
    }

    public function test_replaying_a_set_with_the_same_client_ulid_is_idempotent(): void
    {
        $person = Person::factory()->create();
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs($person);

        $session = $this->postJson('/v1/sessions', [])->assertCreated()->json('data');

        $payload = [
            'client_ulid' => (string) Str::ulid(),
            'exercise_id' => $exercise->id,
            'set_index' => 1,
            'reps' => 8,
            'load' => 100,
        ];

        // First write creates the set.
        $first = $this->postJson("/v1/sessions/{$session['id']}/sets", $payload)
            ->assertCreated()->json('data');

        // A retried offline sync of the SAME mutation must not duplicate it.
        $second = $this->postJson("/v1/sessions/{$session['id']}/sets", $payload)
            ->assertOk()->json('data');

        $this->assertEquals($first['id'], $second['id']);
        $this->assertDatabaseCount('set_logs', 1);
    }

    public function test_a_person_cannot_log_to_another_persons_session(): void
    {
        $owner = Person::factory()->create();
        $intruder = Person::factory()->create();
        $exercise = Exercise::factory()->create();

        Sanctum::actingAs($owner);
        $session = $this->postJson('/v1/sessions', [])->assertCreated()->json('data');

        Sanctum::actingAs($intruder);
        $this->postJson("/v1/sessions/{$session['id']}/sets", [
            'exercise_id' => $exercise->id,
            'set_index' => 1,
            'reps' => 5,
        ])->assertNotFound(); // existence hidden across owners (NFR-SEC-002 / INV-001 spirit)
    }

    public function test_logging_requires_authentication(): void
    {
        $this->postJson('/v1/sessions/01J0000000000000000000/sets', [
            'exercise_id' => '01J0000000000000000000',
            'set_index' => 1,
            'reps' => 5,
        ])->assertUnauthorized();
    }
}
