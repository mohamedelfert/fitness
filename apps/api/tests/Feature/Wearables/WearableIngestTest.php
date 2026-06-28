<?php

namespace Tests\Feature\Wearables;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Wearables\Models\WearableStream;
use Tests\TestCase;

/**
 * Wearable ingest (FR-BIO-003; DATABASE_DESIGN §2.1) — append-only, person-scoped time series of
 * device readings (steps/HR/sleep/HRV → wearable_streams). The server-side ingest API; the
 * Apple Health / Health Connect connectors are client-side. Batch by nature (devices sync many
 * samples at once), idempotent per-reading on client_ulid (offline sync, ADR-005).
 */
class WearableIngestTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<array<string,mixed>>  $readings */
    private function ingest(array $readings)
    {
        return $this->postJson('/v1/wearables/ingest', ['readings' => $readings]);
    }

    public function test_ingests_a_batch_of_readings(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->ingest([
            ['metric' => 'steps', 'value' => 5000, 'recorded_at' => '2026-06-28T08:00:00Z'],
            ['metric' => 'hr', 'value' => 62, 'recorded_at' => '2026-06-28T08:01:00Z', 'source' => 'apple_health'],
        ])->assertCreated()->assertJsonPath('data.ingested', 2);

        $this->assertSame(2, WearableStream::where('person_id', $person->id)->count());
        $this->assertDatabaseHas('wearable_streams', ['person_id' => $person->id, 'metric' => 'hr', 'source' => 'apple_health']);
    }

    public function test_is_idempotent_per_reading_on_client_ulid(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $batch = [
            ['metric' => 'steps', 'value' => 100, 'recorded_at' => '2026-06-28T08:00:00Z', 'client_ulid' => '01HAAA0000000000000000000A'],
            ['metric' => 'steps', 'value' => 200, 'recorded_at' => '2026-06-28T08:05:00Z', 'client_ulid' => '01HAAA0000000000000000000B'],
        ];

        $this->ingest($batch)->assertCreated()->assertJsonPath('data.ingested', 2);
        // Re-sync the same batch (overlap) — duplicates are skipped, not re-inserted.
        $this->ingest($batch)->assertCreated()->assertJsonPath('data.ingested', 0)->assertJsonPath('data.skipped', 2);

        $this->assertSame(2, WearableStream::where('person_id', $person->id)->count());
    }

    public function test_lists_readings_by_metric(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->ingest([
            ['metric' => 'steps', 'value' => 5000, 'recorded_at' => '2026-06-28T08:00:00Z'],
            ['metric' => 'hrv', 'value' => 55, 'recorded_at' => '2026-06-28T08:00:00Z'],
        ])->assertCreated();

        $this->getJson('/v1/wearables?metric=steps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.metric', 'steps');
    }

    public function test_rejects_unknown_metric(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->ingest([['metric' => 'temperature', 'value' => 37, 'recorded_at' => '2026-06-28T08:00:00Z']])->assertStatus(422);
    }

    public function test_validates_reading_fields(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->ingest([['metric' => 'steps', 'recorded_at' => '2026-06-28T08:00:00Z']])->assertStatus(422); // no value
        $this->ingest([])->assertStatus(422); // empty batch
    }

    public function test_only_returns_the_authenticated_persons_readings(): void
    {
        $me = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($other);
        $this->ingest([['metric' => 'steps', 'value' => 9999, 'recorded_at' => '2026-06-28T08:00:00Z']])->assertCreated();

        Sanctum::actingAs($me);
        $this->getJson('/v1/wearables')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $this->ingest([['metric' => 'steps', 'value' => 1, 'recorded_at' => '2026-06-28T08:00:00Z']])->assertUnauthorized();
        $this->getJson('/v1/wearables')->assertUnauthorized();
    }
}
