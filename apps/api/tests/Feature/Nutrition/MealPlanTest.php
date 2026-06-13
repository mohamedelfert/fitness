<?php

namespace Tests\Feature\Nutrition;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodItem;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;
use Tests\TestCase;

/**
 * Meal plan read model (FR-AI-002, FR-NUT): meal_plans → meal_plan_days → meal_plan_items.
 * The nutrition analog of programs; person-owned (Plane A), AI/coach-populated (E1.6/P2).
 */
class MealPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_authenticated_persons_meal_plans(): void
    {
        $me = Person::factory()->create();
        $other = Person::factory()->create();
        MealPlan::factory()->for($me, 'person')->create(['name' => 'My Cut']);
        MealPlan::factory()->for($other, 'person')->create(['name' => 'Their Bulk']);

        Sanctum::actingAs($me);

        $this->getJson('/v1/meal-plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Cut');
    }

    public function test_meal_plan_detail_nests_days_and_items(): void
    {
        $me = Person::factory()->create();
        $plan = MealPlan::factory()->for($me, 'person')->create([
            'name' => 'Lean Bulk',
            'daily_targets_json' => ['kcal' => 2600, 'protein' => 180, 'carbs' => 300, 'fat' => 70],
        ]);
        $day = MealPlanDay::factory()->for($plan)->create(['day_index' => 1, 'name' => 'Day 1', 'ordering' => 1]);
        MealPlanDay::factory()->for($plan)->create(['day_index' => 2, 'name' => 'Day 2', 'ordering' => 2]);
        $chicken = FoodItem::factory()->create(['name_i18n' => ['en' => 'Chicken Breast']]);
        MealPlanItem::factory()->for($day)->create([
            'food_item_id' => $chicken->id, 'meal_type' => 'lunch', 'servings' => 2, 'ordering' => 1,
        ]);

        Sanctum::actingAs($me);

        $this->getJson("/v1/meal-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Lean Bulk')
            ->assertJsonPath('data.daily_targets.kcal', 2600)
            ->assertJsonCount(2, 'data.days')
            ->assertJsonPath('data.days.0.name', 'Day 1')
            ->assertJsonPath('data.days.0.items.0.food_name', 'Chicken Breast')
            ->assertJsonPath('data.days.0.items.0.meal_type', 'lunch');
    }

    public function test_cannot_view_another_persons_meal_plan(): void
    {
        $plan = MealPlan::factory()->create();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson("/v1/meal-plans/{$plan->id}")->assertNotFound();
    }

    public function test_meal_plans_require_authentication(): void
    {
        $this->getJson('/v1/meal-plans')->assertUnauthorized();
    }
}
