<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Person profile read/update (FR-IDN-002). `GET/PATCH /v1/me` per API_SPECIFICATION §2.
 * Returns the demographic basics plus the onboarding training profile the AI Brain consumes.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_person_profile(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed']);
        Sanctum::actingAs($person);

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $person->id)
            ->assertJsonPath('data.email', $person->email)
            ->assertJsonPath('data.health_screen_status', 'passed')
            ->assertJsonPath('data.onboarding_completed', false)
            ->assertJsonStructure(['data' => ['id', 'display_name', 'email', 'training_profile', 'onboarding_completed']]);
    }

    public function test_can_patch_basic_profile_fields(): void
    {
        $person = Person::factory()->create(['sex' => null, 'height_cm' => null]);
        Sanctum::actingAs($person);

        $this->patchJson('/v1/me', [
            'sex' => 'female',
            'height_cm' => 168,
            'unit_system' => 'imperial',
        ])->assertOk()->assertJsonPath('data.sex', 'female');

        $person->refresh();
        $this->assertSame('female', $person->sex);
        $this->assertSame(168, $person->height_cm);
        $this->assertSame('imperial', $person->unit_system);
    }

    public function test_can_patch_training_profile_fields(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);

        $this->patchJson('/v1/me', [
            'experience_level' => 'beginner',
            'equipment' => ['bodyweight', 'dumbbells'],
            'training_days_per_week' => 3,
        ])->assertOk()
            ->assertJsonPath('data.training_profile.experience_level', 'beginner')
            ->assertJsonPath('data.training_profile.training_days_per_week', 3);

        $profile = $person->refresh()->trainingProfile();
        $this->assertSame('beginner', $profile['experience_level']);
        $this->assertSame(['bodyweight', 'dumbbells'], $profile['equipment']);
    }

    public function test_invalid_experience_level_is_rejected(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->patchJson('/v1/me', ['experience_level' => 'godlike'])
            ->assertStatus(422);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/v1/me')->assertUnauthorized();
        $this->patchJson('/v1/me', ['sex' => 'male'])->assertUnauthorized();
    }
}
