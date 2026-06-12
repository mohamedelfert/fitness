<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * PAR-Q+ pre-exercise health screen and the AI safety gate (FR-AI-007).
 * Safety-critical: no AI plan may be generated until a Person is cleared.
 */
class HealthScreenTest extends TestCase
{
    use RefreshDatabase;

    private function allNo(): array
    {
        return ['answers' => [
            'q1_heart_condition_or_high_bp' => false,
            'q2_chest_pain' => false,
            'q3_dizziness_or_loss_of_consciousness' => false,
            'q4_other_chronic_condition' => false,
            'q5_chronic_condition_medication' => false,
            'q6_bone_joint_soft_tissue_problem' => false,
            'q7_doctor_supervised_activity_only' => false,
        ]];
    }

    public function test_questionnaire_lists_the_seven_parq_questions(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/health-screen/questions')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonStructure(['data' => [['key', 'text', 'order']]]);
    }

    public function test_all_no_answers_clear_the_screen(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'none']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/me/health-screen', $this->allNo())
            ->assertCreated()
            ->assertJsonPath('data.result', 'passed');

        $this->assertSame('passed', $person->refresh()->health_screen_status);
    }

    public function test_any_yes_flags_the_screen(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'none']);
        Sanctum::actingAs($person);

        $payload = $this->allNo();
        $payload['answers']['q2_chest_pain'] = true;

        $this->postJson('/v1/me/health-screen', $payload)
            ->assertCreated()
            ->assertJsonPath('data.result', 'flagged')
            ->assertJsonPath('data.flagged_questions', ['q2_chest_pain']);

        $this->assertSame('flagged', $person->refresh()->health_screen_status);
    }

    public function test_incomplete_answers_are_rejected(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/me/health-screen', ['answers' => ['q1_heart_condition_or_high_bp' => false]])
            ->assertStatus(422);
    }

    public function test_screen_requires_authentication(): void
    {
        $this->postJson('/v1/me/health-screen', $this->allNo())->assertUnauthorized();
    }

    public function test_only_a_cleared_person_may_generate_an_ai_plan(): void
    {
        $cleared = Person::factory()->create(['health_screen_status' => 'passed']);
        $flagged = Person::factory()->create(['health_screen_status' => 'flagged']);
        $unscreened = Person::factory()->create(['health_screen_status' => 'none']);

        $this->assertTrue(Gate::forUser($cleared)->allows('ai-plan.generate'));
        $this->assertFalse(Gate::forUser($flagged)->allows('ai-plan.generate'));
        $this->assertFalse(Gate::forUser($unscreened)->allows('ai-plan.generate'));
    }
}
