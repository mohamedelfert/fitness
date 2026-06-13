<?php

namespace Tests\Feature\Training;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\PersonalRecord;
use Modules\Training\Models\Session;
use Tests\TestCase;

/**
 * Personal-record auto-detection (FR-TRN-004). On session finish, a queued job derives
 * the Person's current PRs (max load, estimated 1RM, max reps) from the append-only
 * set_logs into the `personal_records` read-model (DATABASE_DESIGN.md §2.2). Runs sync
 * in tests (QUEUE_CONNECTION=sync).
 */
class PersonalRecordsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<string, mixed>> records keyed by metric */
    private function recordsByMetric(): array
    {
        $data = $this->getJson('/v1/me/records')->assertOk()->json('data');

        return collect($data)->keyBy('metric')->all();
    }

    private function logSet(string $sessionId, string $exerciseId, int $reps, ?float $load): void
    {
        $this->postJson("/v1/sessions/{$sessionId}/sets", [
            'exercise_id' => $exerciseId,
            'set_index' => 1,
            'reps' => $reps,
            'load' => $load,
        ])->assertCreated();
    }

    public function test_finishing_a_session_detects_personal_records(): void
    {
        $person = Person::factory()->create();
        $exercise = Exercise::factory()->create(['name' => 'Back Squat']);
        Sanctum::actingAs($person);

        $session = $this->postJson('/v1/sessions', [])->json('data');
        $this->logSet($session['id'], $exercise->id, 10, 60.0);
        $this->logSet($session['id'], $exercise->id, 5, 100.0);
        $this->logSet($session['id'], $exercise->id, 12, 40.0);

        $this->postJson("/v1/sessions/{$session['id']}/finish")->assertOk();
        $this->assertNotNull(Session::find($session['id'])->ended_at);

        $records = $this->recordsByMetric();
        $this->assertEquals(100, $records['max_load']['value']);
        $this->assertEquals(12, $records['max_reps']['value']);
        // Epley best = max(60*(1+10/30)=80, 100*(1+5/30)=116.67, 40*(1+12/30)=56)
        $this->assertEqualsWithDelta(116.67, $records['est_1rm']['value'], 0.01);
        $this->assertSame('Back Squat', $records['max_load']['exercise_name']);
    }

    public function test_a_better_set_replaces_the_pr_without_duplicating(): void
    {
        $person = Person::factory()->create();
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs($person);

        $s1 = $this->postJson('/v1/sessions', [])->json('data');
        $this->logSet($s1['id'], $exercise->id, 5, 60.0);
        $this->postJson("/v1/sessions/{$s1['id']}/finish")->assertOk();

        $s2 = $this->postJson('/v1/sessions', [])->json('data');
        $this->logSet($s2['id'], $exercise->id, 5, 80.0);
        $this->postJson("/v1/sessions/{$s2['id']}/finish")->assertOk();

        $this->assertEquals(80, $this->recordsByMetric()['max_load']['value']);
        $this->assertSame(1, PersonalRecord::where('person_id', $person->id)
            ->where('exercise_id', $exercise->id)->where('metric', 'max_load')->count());
    }

    public function test_records_are_scoped_to_the_person(): void
    {
        $owner = Person::factory()->create();
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs($owner);
        $session = $this->postJson('/v1/sessions', [])->json('data');
        $this->logSet($session['id'], $exercise->id, 5, 90.0);
        $this->postJson("/v1/sessions/{$session['id']}/finish")->assertOk();

        Sanctum::actingAs(Person::factory()->create());
        $this->getJson('/v1/me/records')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_finishing_an_empty_session_creates_no_records(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $session = $this->postJson('/v1/sessions', [])->json('data');

        $this->postJson("/v1/sessions/{$session['id']}/finish")->assertOk();
        $this->getJson('/v1/me/records')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_finish_is_owner_scoped(): void
    {
        $owner = Person::factory()->create();
        Sanctum::actingAs($owner);
        $session = $this->postJson('/v1/sessions', [])->json('data');

        Sanctum::actingAs(Person::factory()->create());
        $this->postJson("/v1/sessions/{$session['id']}/finish")->assertNotFound();
    }

    public function test_finish_requires_authentication(): void
    {
        $this->postJson('/v1/sessions/01J0000000000000000000/finish')->assertUnauthorized();
        $this->getJson('/v1/me/records')->assertUnauthorized();
    }
}
