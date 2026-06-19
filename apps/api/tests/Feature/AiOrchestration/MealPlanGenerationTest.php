<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodItem;
use Tests\TestCase;

/**
 * AI meal-plan generation (FR-AI-002) + the dietary safety post-eval (FR-AI-007 / INV-005),
 * the nutrition analog of ProgramGenerationTest. Same fake-gateway seam (ADR-004); these
 * prove the orchestration + dietary-safety *mechanism*, not a clinical/licensed food
 * ontology (that arrives with Q4). The credit meter (FR-SAS-004) wraps it like programs.
 */
class MealPlanGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedPerson(string $status = 'passed', array $restrictions = []): Person
    {
        $person = Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => [
                'completed' => true,
                'profile' => [
                    'experience_level' => 'beginner',
                    'equipment' => ['bodyweight'],
                    'dietary_restrictions' => $restrictions,
                ],
            ],
        ]);

        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function food(string $slug, string $name, array $dietaryTags = []): FoodItem
    {
        return FoodItem::factory()->create([
            'slug' => $slug,
            'name_i18n' => ['en' => $name],
            'dietary_tags' => $dietaryTags,
        ]);
    }

    private function mealPlanJson(array $slugs): string
    {
        return json_encode([
            'name' => 'AI Cutting Plan',
            'daily_targets' => ['kcal' => 2000, 'protein' => 150, 'carbs' => 200, 'fat' => 60],
            'days' => [[
                'day_index' => 1,
                'name' => 'Day 1',
                'meals' => array_map(fn ($s) => [
                    'meal_type' => 'breakfast', 'food_slug' => $s, 'servings' => 1,
                    'target_kcal' => 300, 'target_macros' => ['protein' => 20, 'carbs' => 30, 'fat' => 10],
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

                return new LlmResult($text, 300, 500, 'stub-1');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_cleared_onboarded_person_generates_and_persists_a_meal_plan(): void
    {
        $person = $this->onboardedPerson();
        $this->food('rolled-oats', 'Rolled Oats');
        $this->scriptGateway([$this->mealPlanJson(['rolled-oats'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')
            ->assertCreated()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.days.0.items.0.food_name', 'Rolled Oats');

        $this->assertDatabaseHas('meal_plans', ['person_id' => $person->id, 'source' => 'ai']);
        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'meal_plan', 'safety_verdict' => 'passed',
        ]);
        $this->assertSame(10 - (int) config('ai.credits.meal_plan'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_response_localizes_food_names_to_the_request_language(): void
    {
        $person = $this->onboardedPerson();
        FoodItem::factory()->create([
            'slug' => 'rolled-oats',
            'name_i18n' => ['en' => 'Rolled Oats', 'ar' => 'شوفان'],
            'dietary_tags' => [],
        ]);
        $this->scriptGateway([$this->mealPlanJson(['rolled-oats'])]);
        Sanctum::actingAs($person);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/v1/ai/meal-plan')
            ->assertCreated()
            ->assertJsonPath('data.days.0.items.0.food_name', 'شوفان');
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway([$this->mealPlanJson(['rolled-oats'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertForbidden();
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_screen_passed_but_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertStatus(422);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_dietary_restriction_triggers_regeneration(): void
    {
        $person = $this->onboardedPerson('passed', ['dairy']);
        $this->food('greek-yogurt', 'Greek Yogurt', ['dairy']);
        $this->food('rolled-oats', 'Rolled Oats');
        // First attempt prescribes a dairy food; second is safe.
        $this->scriptGateway([$this->mealPlanJson(['greek-yogurt']), $this->mealPlanJson(['rolled-oats'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')
            ->assertCreated()
            ->assertJsonPath('data.days.0.items.0.food_name', 'Rolled Oats');

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'passed']);
        // Debited exactly once despite the regenerate loop.
        $this->assertSame(10 - (int) config('ai.credits.meal_plan'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_persistent_dietary_violation_is_rejected_without_persisting(): void
    {
        $person = $this->onboardedPerson('passed', ['dairy']);
        $this->food('greek-yogurt', 'Greek Yogurt', ['dairy']);
        $this->scriptGateway([$this->mealPlanJson(['greek-yogurt'])]); // always unsafe
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertStatus(422);
        $this->assertDatabaseCount('meal_plans', 0); // INV-005: nothing persists
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'safety_verdict' => 'rejected']);
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_hallucinated_food_slug_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway([$this->mealPlanJson(['this-food-does-not-exist'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertStatus(422); // never 500
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_malformed_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['this is not json at all']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertStatus(422); // never 500
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_generation_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['dietary_restrictions' => []]],
        ]); // onboarded but unfunded
        $this->food('rolled-oats', 'Rolled Oats');
        $this->scriptGateway([$this->mealPlanJson(['rolled-oats'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/meal-plan')->assertStatus(402);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_meal_plan_generation_requires_authentication(): void
    {
        $this->postJson('/v1/ai/meal-plan')->assertUnauthorized();
    }
}
