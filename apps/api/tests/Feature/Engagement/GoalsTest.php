<?php

namespace Tests\Feature\Engagement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Goals (FR-ENG-001). A Person-owned (Plane A) target captured at onboarding
 * and tracked thereafter. Scoped to the authenticated Person.
 */
class GoalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_can_create_a_goal(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/goals', [
            'type' => 'fat_loss',
            'target_value' => 8,
            'target_unit' => 'kg',
            'target_date' => '2026-12-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'fat_loss')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('goals', [
            'person_id' => $person->id,
            'type' => 'fat_loss',
            'target_unit' => 'kg',
            'status' => 'active',
        ]);
    }

    public function test_goals_are_listed_only_for_the_authenticated_person(): void
    {
        $mine = Person::factory()->create();
        $other = Person::factory()->create();
        Goal::factory()->for($mine, 'person')->create(['type' => 'strength']);
        Goal::factory()->for($other, 'person')->create(['type' => 'muscle_gain']);

        Sanctum::actingAs($mine);

        $this->getJson('/v1/goals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'strength');
    }

    public function test_goal_type_must_be_in_the_vocabulary(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/goals', ['type' => 'become_a_wizard'])
            ->assertStatus(422);
    }

    public function test_goals_require_authentication(): void
    {
        $this->getJson('/v1/goals')->assertUnauthorized();
        $this->postJson('/v1/goals', ['type' => 'fat_loss'])->assertUnauthorized();
    }

    public function test_person_can_close_a_goal(): void
    {
        $person = Person::factory()->create();
        $goal = Goal::factory()->for($person, 'person')->create(['status' => 'active']);
        Sanctum::actingAs($person);

        $this->patchJson("/v1/goals/{$goal->id}", ['status' => 'achieved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'achieved');

        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'status' => 'achieved']);
    }

    public function test_updating_a_goal_status_must_be_in_the_vocabulary(): void
    {
        $person = Person::factory()->create();
        $goal = Goal::factory()->for($person, 'person')->create(['status' => 'active']);
        Sanctum::actingAs($person);

        $this->patchJson("/v1/goals/{$goal->id}", ['status' => 'vanquished'])->assertStatus(422);
    }

    public function test_cannot_update_another_persons_goal(): void
    {
        $other = Person::factory()->create();
        $goal = Goal::factory()->for($other, 'person')->create(['status' => 'active']);
        Sanctum::actingAs(Person::factory()->create());

        $this->patchJson("/v1/goals/{$goal->id}", ['status' => 'achieved'])->assertNotFound();
    }
}
