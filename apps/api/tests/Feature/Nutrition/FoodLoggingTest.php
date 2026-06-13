<?php

namespace Tests\Feature\Nutrition;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodItem;
use Tests\TestCase;

/**
 * Food logging (FR-NUT-002) + daily summary. FoodLogs are append-only & immutable (INV-002),
 * idempotent on client_ulid (offline sync, ADR-005). Logging from a FoodItem snapshots the
 * macros (servings × per-serving) so the log is preserved even if the item later changes.
 */
class FoodLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_from_a_food_item_scales_macros_by_servings(): void
    {
        $person = Person::factory()->create();
        $item = FoodItem::factory()->create(['kcal' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 4]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/food-logs', [
            'food_item_id' => $item->id,
            'meal_type' => 'lunch',
            'servings' => 2,
            'source' => 'search',
        ])
            ->assertCreated()
            ->assertJsonPath('data.kcal', 330)
            ->assertJsonPath('data.protein', 62)
            ->assertJsonPath('data.fat', 8);

        $this->assertDatabaseHas('food_logs', [
            'person_id' => $person->id, 'food_item_id' => $item->id, 'meal_type' => 'lunch',
        ]);
    }

    public function test_logging_a_custom_food_without_an_item(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/food-logs', [
            'meal_type' => 'snack',
            'servings' => 1,
            'kcal' => 200,
            'protein' => 10,
            'carbs' => 20,
            'fat' => 5,
            'source' => 'custom',
        ])->assertCreated()->assertJsonPath('data.kcal', 200);
    }

    public function test_custom_food_without_kcal_is_rejected(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->postJson('/v1/food-logs', ['meal_type' => 'snack', 'servings' => 1])
            ->assertStatus(422);
    }

    public function test_food_log_is_idempotent_by_client_ulid(): void
    {
        $person = Person::factory()->create();
        $item = FoodItem::factory()->create(['kcal' => 100]);
        Sanctum::actingAs($person);

        $payload = [
            'client_ulid' => (string) Str::ulid(),
            'food_item_id' => $item->id,
            'meal_type' => 'breakfast',
            'servings' => 1,
        ];

        $first = $this->postJson('/v1/food-logs', $payload)->assertCreated()->json('data');
        $second = $this->postJson('/v1/food-logs', $payload)->assertOk()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('food_logs', 1);
    }

    public function test_daily_summary_rolls_up_macros_for_the_date(): void
    {
        $person = Person::factory()->create();
        $item = FoodItem::factory()->create(['kcal' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 4]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/food-logs', ['food_item_id' => $item->id, 'meal_type' => 'lunch', 'servings' => 2])->assertCreated();
        $this->postJson('/v1/food-logs', ['meal_type' => 'snack', 'servings' => 1, 'kcal' => 200, 'protein' => 10, 'carbs' => 20, 'fat' => 5])->assertCreated();

        $this->getJson('/v1/me/nutrition/summary?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.kcal', 530)
            ->assertJsonPath('data.protein', 72)
            ->assertJsonPath('data.entries', 2);
    }

    public function test_food_log_summary_is_person_scoped(): void
    {
        $owner = Person::factory()->create();
        $item = FoodItem::factory()->create(['kcal' => 300]);
        Sanctum::actingAs($owner);
        $this->postJson('/v1/food-logs', ['food_item_id' => $item->id, 'meal_type' => 'lunch', 'servings' => 1])->assertCreated();

        Sanctum::actingAs(Person::factory()->create());
        $this->getJson('/v1/me/nutrition/summary?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.kcal', 0)
            ->assertJsonPath('data.entries', 0);
    }

    public function test_food_logging_requires_authentication(): void
    {
        $this->postJson('/v1/food-logs', ['meal_type' => 'lunch', 'servings' => 1, 'kcal' => 100])->assertUnauthorized();
        $this->getJson('/v1/me/nutrition/summary')->assertUnauthorized();
    }
}
