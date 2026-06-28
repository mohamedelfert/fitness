<?php

namespace Tests\Feature\Biometrics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Biometrics\Models\Biometric;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Biometrics (FR-BIO-001; DATABASE_DESIGN §2.1) — append-only, idempotent-on-client_ulid,
 * person-scoped time series of body measurements (weight / body fat / circumferences). Mirrors
 * the water/supplement intake-log pattern (offline sync, ADR-005).
 */
class BiometricTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_a_measurement(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/biometrics', ['type' => 'weight', 'value' => 72.5, 'unit' => 'kg'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'weight')
            ->assertJsonPath('data.value', 72.5)
            ->assertJsonPath('data.unit', 'kg');

        $this->assertDatabaseHas('biometrics', ['person_id' => $person->id, 'type' => 'weight', 'unit' => 'kg']);
    }

    public function test_is_idempotent_on_client_ulid(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $payload = ['type' => 'weight', 'value' => 80, 'unit' => 'kg', 'client_ulid' => '01HZZZZZZZZZZZZZZZZZZZZZZZ'];

        $this->postJson('/v1/biometrics', $payload)->assertCreated();
        $this->postJson('/v1/biometrics', $payload)->assertOk(); // replay → 200, not a duplicate

        $this->assertSame(1, Biometric::where('person_id', $person->id)->count());
    }

    public function test_lists_biometrics_filtered_by_type(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->postJson('/v1/biometrics', ['type' => 'weight', 'value' => 72.5, 'unit' => 'kg'])->assertCreated();
        $this->postJson('/v1/biometrics', ['type' => 'waist', 'value' => 81, 'unit' => 'cm'])->assertCreated();

        $this->getJson('/v1/biometrics')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/v1/biometrics?type=weight')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'weight');
    }

    public function test_rejects_unknown_type(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/biometrics', ['type' => 'mood', 'value' => 5, 'unit' => 'x'])->assertStatus(422);
    }

    public function test_value_is_required_and_numeric(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/biometrics', ['type' => 'weight', 'unit' => 'kg'])->assertStatus(422);
        $this->postJson('/v1/biometrics', ['type' => 'weight', 'value' => 'heavy', 'unit' => 'kg'])->assertStatus(422);
    }

    public function test_only_returns_the_authenticated_persons_biometrics(): void
    {
        $me = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($other);
        $this->postJson('/v1/biometrics', ['type' => 'weight', 'value' => 99, 'unit' => 'kg'])->assertCreated();

        Sanctum::actingAs($me);
        $this->getJson('/v1/biometrics')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/v1/biometrics', ['type' => 'weight', 'value' => 72.5, 'unit' => 'kg'])->assertUnauthorized();
        $this->getJson('/v1/biometrics')->assertUnauthorized();
    }
}
