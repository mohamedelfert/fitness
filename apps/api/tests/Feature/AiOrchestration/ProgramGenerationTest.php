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
 * AI program generation (FR-AI-001) + the safety post-eval gate (FR-AI-007 / INV-005).
 * Built against a fake LlmGateway (ADR-004 seam) — the real Claude adapter lands with Q5.
 * These prove the orchestration + safety *mechanism*, not clinical coverage (that is Q7).
 */
class ProgramGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedPerson(string $status = 'passed', array $injuries = []): Person
    {
        $person = Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => [
                'completed' => true,
                'profile' => ['experience_level' => 'beginner', 'equipment' => ['bodyweight'], 'injuries' => $injuries],
            ],
        ]);

        // Generation now meters AICredits (FR-SAS-004); fund the wallet so these tests
        // exercise the safety/orchestration paths, not the credit gate (see AiCreditTest).
        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function programJson(array $slugs): string
    {
        return json_encode([
            'name' => 'AI Starter Program',
            'workouts' => [[
                'day_index' => 1,
                'name' => 'Full Body',
                'exercises' => array_map(fn ($s) => [
                    'exercise_slug' => $s, 'target_sets' => 3, 'target_reps' => '8-12', 'rest_sec' => 90,
                ], $slugs),
            ]],
        ]);
    }

    /** Bind a gateway that returns the given response texts in sequence (last repeats). */
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

                return new LlmResult($text, 400, 600, 'stub-1');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_cleared_onboarded_person_generates_and_persists_a_program(): void
    {
        $person = $this->onboardedPerson();
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        $this->scriptGateway([$this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')
            ->assertCreated()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.workouts.0.exercises.0.exercise_name', 'Push-up');

        $this->assertDatabaseHas('programs', ['person_id' => $person->id, 'source' => 'ai']);
        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'program', 'safety_verdict' => 'passed',
        ]);
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway([$this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertForbidden();
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_screen_passed_but_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(422);
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_contraindicated_exercise_triggers_regeneration(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        // First attempt prescribes a knee-contraindicated movement; second is safe.
        $this->scriptGateway([$this->programJson(['back-squat']), $this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')
            ->assertCreated()
            ->assertJsonPath('data.workouts.0.exercises.0.exercise_name', 'Push-up');

        // Two Brain calls: one rejected, one passed.
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'passed']);
    }

    public function test_persistently_contraindicated_generation_is_rejected_without_persisting(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        $this->scriptGateway([$this->programJson(['back-squat'])]); // always unsafe
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(422);
        $this->assertDatabaseCount('programs', 0); // INV-005: nothing persists
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
    }

    public function test_hallucinated_exercise_slug_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway([$this->programJson(['this-exercise-does-not-exist'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(422); // never 500
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_malformed_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['this is not json at all']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(422); // never 500
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_program_generation_requires_authentication(): void
    {
        $this->postJson('/v1/ai/program')->assertUnauthorized();
    }
}
