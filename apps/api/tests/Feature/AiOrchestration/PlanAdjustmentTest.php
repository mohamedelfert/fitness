<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Program;
use Tests\TestCase;

/**
 * AI plan-adjustment proposals (FR-AI-006) — the fourth AiOrchestration generator and the twin
 * of exercise-alternatives: it persists nothing, returning proposed changes (200) the member
 * reviews before applying. It runs the full safety sandwich over the *prescribed* exercises (a
 * contraindicated swap/addition is an INV-005 hazard) via ContraindicationScanner, and is metered
 * like the others. The program is person-owned, so an unknown/cross-person id is 404 (not 422,
 * API_SPECIFICATION §13). "No changes recommended" is a legitimate answer → empty list, 200.
 * Built against the fake LlmGateway seam (ADR-004).
 */
class PlanAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedPerson(string $status = 'passed', array $injuries = []): Person
    {
        $person = Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => [
                'completed' => true,
                'profile' => ['experience_level' => 'beginner', 'equipment' => ['barbell'], 'injuries' => $injuries],
            ],
        ]);

        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function adjustmentJson(array $slugs): string
    {
        return json_encode([
            'adjustments' => array_map(fn ($s) => [
                'exercise_slug' => $s,
                'action' => 'swap',
                'rationale' => 'Better progression for the athlete with available equipment.',
            ], $slugs),
        ]);
    }

    private function scriptGateway(array $texts): void
    {
        $gateway = new class($texts) implements LlmGateway
        {
            private int $i = 0;

            public function __construct(private array $texts) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $text = $this->texts[min($this->i, count($this->texts) - 1)];
                $this->i++;

                return new LlmResult($text, 300, 250, 'stub-strong');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_proposes_safe_adjustments_for_a_program(): void
    {
        $person = $this->onboardedPerson();
        $program = Program::factory()->create(['person_id' => $person->id]);
        Exercise::factory()->create(['slug' => 'front-squat', 'name' => 'Front Squat', 'contraindications' => []]);
        $this->scriptGateway([$this->adjustmentJson(['front-squat'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])
            ->assertOk()
            ->assertJsonPath('data.program.id', $program->id)
            ->assertJsonPath('data.adjustments.0.slug', 'front-squat')
            ->assertJsonPath('data.adjustments.0.name', 'Front Squat');

        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'plan_adjustment', 'safety_verdict' => 'passed',
        ]);
        $this->assertSame(10 - (int) config('ai.credits.plan_adjustment'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_no_changes_recommended_returns_empty_list_200(): void
    {
        $person = $this->onboardedPerson();
        $program = Program::factory()->create(['person_id' => $person->id]);
        $this->scriptGateway([$this->adjustmentJson([])]); // model says the plan is fine
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])
            ->assertOk()
            ->assertJsonPath('data.adjustments', []);

        // A valid review is a success → debited and logged passed.
        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'plan_adjustment', 'safety_verdict' => 'passed',
        ]);
        $this->assertSame(10 - (int) config('ai.credits.plan_adjustment'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $program = Program::factory()->create(['person_id' => $person->id]);
        $this->scriptGateway([$this->adjustmentJson(['front-squat'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        $program = Program::factory()->create(['person_id' => $person->id]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertStatus(422);
    }

    public function test_unknown_program_is_404(): void
    {
        $person = $this->onboardedPerson();
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => 'no-such-program'])->assertNotFound();
    }

    public function test_another_persons_program_is_404(): void
    {
        $person = $this->onboardedPerson();
        $other = Person::factory()->create();
        $program = Program::factory()->create(['person_id' => $other->id]);
        $this->scriptGateway([$this->adjustmentJson(['front-squat'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertNotFound();
    }

    public function test_contraindicated_adjustment_triggers_regeneration(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        $program = Program::factory()->create(['person_id' => $person->id]);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        Exercise::factory()->create(['slug' => 'hip-thrust', 'name' => 'Hip Thrust', 'contraindications' => []]);
        // First proposal is knee-contraindicated; second is safe.
        $this->scriptGateway([$this->adjustmentJson(['back-squat']), $this->adjustmentJson(['hip-thrust'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])
            ->assertOk()
            ->assertJsonPath('data.adjustments.0.slug', 'hip-thrust');

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'passed']);
        $this->assertSame(10 - (int) config('ai.credits.plan_adjustment'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_persistently_contraindicated_adjustments_are_rejected(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        $program = Program::factory()->create(['person_id' => $person->id]);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        $this->scriptGateway([$this->adjustmentJson(['back-squat'])]); // always unsafe
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertStatus(422);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_hallucinated_adjustment_slug_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $program = Program::factory()->create(['person_id' => $person->id]);
        $this->scriptGateway([$this->adjustmentJson(['this-exercise-does-not-exist'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertStatus(422); // never 500
    }

    public function test_malformed_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $program = Program::factory()->create(['person_id' => $person->id]);
        $this->scriptGateway(['not json at all']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertStatus(422); // never 500
    }

    public function test_adjustment_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]); // onboarded but unfunded
        $program = Program::factory()->create(['person_id' => $person->id]);
        $this->scriptGateway([$this->adjustmentJson(['front-squat'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => $program->id])->assertStatus(402);
    }

    public function test_plan_adjustment_requires_authentication(): void
    {
        $this->postJson('/v1/ai/plan-adjustment', ['program_id' => 'whatever'])->assertUnauthorized();
    }
}
