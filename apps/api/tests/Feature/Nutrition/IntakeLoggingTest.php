<?php

namespace Tests\Feature\Nutrition;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Water (FR-NUT-006) and supplement (FR-NUT-007) intake logging — append-only, idempotent
 * (client_ulid). Water rolls up into the daily nutrition summary.
 */
class IntakeLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_water(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/water-logs', ['amount_ml' => 500])
            ->assertCreated()
            ->assertJsonPath('data.amount_ml', 500);

        $this->assertDatabaseHas('water_logs', ['person_id' => $person->id, 'amount_ml' => 500]);
    }

    public function test_water_log_is_idempotent_by_client_ulid(): void
    {
        Sanctum::actingAs(Person::factory()->create());
        $payload = ['client_ulid' => (string) Str::ulid(), 'amount_ml' => 250];

        $first = $this->postJson('/v1/water-logs', $payload)->assertCreated()->json('data');
        $second = $this->postJson('/v1/water-logs', $payload)->assertOk()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('water_logs', 1);
    }

    public function test_daily_summary_includes_total_water(): void
    {
        Sanctum::actingAs(Person::factory()->create());
        $this->postJson('/v1/water-logs', ['amount_ml' => 500])->assertCreated();
        $this->postJson('/v1/water-logs', ['amount_ml' => 300])->assertCreated();

        $this->getJson('/v1/me/nutrition/summary?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.water_ml', 800);
    }

    public function test_log_supplement(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/supplement-logs', ['name' => 'Creatine', 'dose' => 5, 'unit' => 'g'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Creatine')
            ->assertJsonPath('data.unit', 'g');

        $this->assertDatabaseHas('supplement_logs', ['person_id' => $person->id, 'name' => 'Creatine']);
    }

    public function test_supplement_requires_a_name(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/supplement-logs', ['dose' => 5])->assertStatus(422);
    }

    public function test_intake_logging_requires_authentication(): void
    {
        $this->postJson('/v1/water-logs', ['amount_ml' => 500])->assertUnauthorized();
        $this->postJson('/v1/supplement-logs', ['name' => 'Creatine'])->assertUnauthorized();
    }
}
