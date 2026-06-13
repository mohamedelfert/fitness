<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Tests\TestCase;

/**
 * Onboarding profile capture (FR-IDN, J1 step 2). The multi-step submit that fills the
 * Person's training profile, creates their goal(s), and assembles the AI input contract
 * the Brain consumes — including injuries for contraindication gating (FR-AI-007).
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sex' => 'female',
            'dob' => '1998-04-12',
            'height_cm' => 168,
            'experience_level' => 'beginner',
            'equipment' => ['bodyweight', 'dumbbells'],
            'training_days_per_week' => 3,
            'dietary_preferences' => ['halal'],
            'dietary_restrictions' => ['lactose'],
            'injuries' => ['left_knee'],
            'goals' => [
                ['type' => 'fat_loss', 'target_value' => 8, 'target_unit' => 'kg', 'target_date' => '2026-12-01'],
            ],
        ], $overrides);
    }

    public function test_onboarding_persists_profile_creates_goals_and_marks_complete(): void
    {
        $person = Person::factory()->create(['sex' => null, 'height_cm' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/onboarding', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.onboarding_completed', true);

        $person->refresh();
        $this->assertSame('female', $person->sex);
        $this->assertSame(168, $person->height_cm);
        $this->assertTrue($person->isOnboardingComplete());
        $this->assertSame('beginner', $person->trainingProfile()['experience_level']);
        $this->assertDatabaseHas('goals', ['person_id' => $person->id, 'type' => 'fat_loss']);
    }

    public function test_resubmitting_onboarding_does_not_duplicate_a_goal(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->postJson('/v1/onboarding', $this->payload())->assertCreated();
        // A network retry / edit re-submits the same goal type with a revised target.
        $this->postJson('/v1/onboarding', $this->payload([
            'goals' => [['type' => 'fat_loss', 'target_value' => 6, 'target_unit' => 'kg']],
        ]))->assertCreated();

        $this->assertDatabaseCount('goals', 1);
        $this->assertSame('6.00', Goal::where('person_id', $person->id)->value('target_value'));
    }

    public function test_onboarding_requires_core_fields(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        // Missing experience_level and goals.
        $this->postJson('/v1/onboarding', ['sex' => 'male'])
            ->assertStatus(422);
    }

    public function test_onboarding_requires_authentication(): void
    {
        $this->postJson('/v1/onboarding', $this->payload())->assertUnauthorized();
    }

    public function test_ai_input_profile_carries_injuries_and_screen_status_for_a_cleared_person(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed']);
        Sanctum::actingAs($person);
        $this->postJson('/v1/onboarding', $this->payload())->assertCreated();

        $contract = AiInputProfile::for($person->refresh());

        $this->assertSame('passed', $contract['health_screen_status']);
        $this->assertContains('left_knee', $contract['injuries']);
        $this->assertSame('beginner', $contract['experience_level']);
        $this->assertContains('fat_loss', array_column($contract['goals'], 'type'));
        $this->assertTrue($contract['ready_for_ai']);
    }

    public function test_ai_input_profile_is_not_ready_when_screen_not_passed(): void
    {
        $flagged = Person::factory()->create(['health_screen_status' => 'flagged']);
        Sanctum::actingAs($flagged);
        $this->postJson('/v1/onboarding', $this->payload())->assertCreated();

        $this->assertFalse(AiInputProfile::for($flagged->refresh())['ready_for_ai']);
    }
}
