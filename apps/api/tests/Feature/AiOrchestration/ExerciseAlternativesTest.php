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
use Tests\TestCase;

/**
 * AI exercise-alternatives (FR-AI-003) — the third AiOrchestration generator. Unlike program/
 * meal-plan it persists nothing: it returns a ranked list of safe swap suggestions (200, not
 * 201). It still runs the full safety sandwich — a contraindicated suggestion is an INV-005
 * hazard — reusing ContraindicationScanner, and it is metered like the others (cheap tier).
 * Built against the fake LlmGateway seam (ADR-004); proves the mechanism, not clinical depth.
 */
class ExerciseAlternativesTest extends TestCase
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

        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function altJson(array $slugs): string
    {
        return json_encode([
            'alternatives' => array_map(fn ($s) => [
                'exercise_slug' => $s, 'rationale' => 'Targets similar muscles with available equipment.',
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

                return new LlmResult($text, 150, 200, 'stub-cheap');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generates_safe_alternatives_for_an_exercise(): void
    {
        $person = $this->onboardedPerson();
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press', 'contraindications' => []]);
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        $this->scriptGateway([$this->altJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])
            ->assertOk()
            ->assertJsonPath('data.source.slug', 'bench-press')
            ->assertJsonPath('data.alternatives.0.slug', 'push-up')
            ->assertJsonPath('data.alternatives.0.name', 'Push-up');

        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'exercise_alternatives', 'safety_verdict' => 'passed',
        ]);
        $this->assertSame(10 - (int) config('ai.credits.exercise_alternatives'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press']);
        $this->scriptGateway([$this->altJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertStatus(422);
    }

    public function test_unknown_source_exercise_is_rejected(): void
    {
        $person = $this->onboardedPerson();
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'no-such-exercise'])->assertStatus(422);
    }

    public function test_contraindicated_alternative_triggers_regeneration(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        Exercise::factory()->create(['slug' => 'leg-press', 'name' => 'Leg Press', 'contraindications' => []]);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        Exercise::factory()->create(['slug' => 'glute-bridge', 'name' => 'Glute Bridge', 'contraindications' => []]);
        // First suggestion is knee-contraindicated; second is safe.
        $this->scriptGateway([$this->altJson(['back-squat']), $this->altJson(['glute-bridge'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'leg-press'])
            ->assertOk()
            ->assertJsonPath('data.alternatives.0.slug', 'glute-bridge');

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'passed']);
        $this->assertSame(10 - (int) config('ai.credits.exercise_alternatives'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_persistently_contraindicated_alternatives_are_rejected(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        Exercise::factory()->create(['slug' => 'leg-press', 'name' => 'Leg Press', 'contraindications' => []]);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        $this->scriptGateway([$this->altJson(['back-squat'])]); // always unsafe
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'leg-press'])->assertStatus(422);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_hallucinated_alternative_slug_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press']);
        $this->scriptGateway([$this->altJson(['this-exercise-does-not-exist'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertStatus(422); // never 500
    }

    public function test_malformed_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press']);
        $this->scriptGateway(['not json at all']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertStatus(422); // never 500
    }

    public function test_generation_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]); // onboarded but unfunded
        Exercise::factory()->create(['slug' => 'bench-press', 'name' => 'Bench Press']);
        $this->scriptGateway([$this->altJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertStatus(402);
    }

    public function test_exercise_alternatives_requires_authentication(): void
    {
        $this->postJson('/v1/ai/exercise-alternatives', ['exercise_slug' => 'bench-press'])->assertUnauthorized();
    }
}
