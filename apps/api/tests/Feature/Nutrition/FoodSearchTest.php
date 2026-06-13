<?php

namespace Tests\Feature\Nutrition;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodItem;
use Tests\TestCase;

/**
 * Food database search + barcode lookup (FR-NUT-001/003). Read-only shared asset (Plane A),
 * localized via Accept-Language. DB-backed search over name_i18n (Meilisearch in prod) — this
 * is where Arabic-name matching legitimately lives (food_items carry name_i18n, unlike exercises).
 */
class FoodSearchTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoods(): void
    {
        FoodItem::factory()->create([
            'name_i18n' => ['en' => 'Chicken Breast', 'ar' => 'صدر دجاج'],
            'barcode' => '1111111111', 'kcal' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6,
        ]);
        FoodItem::factory()->create([
            'name_i18n' => ['en' => 'White Rice', 'ar' => 'أرز أبيض'],
            'barcode' => '2222222222', 'kcal' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3,
        ]);
        FoodItem::factory()->create([
            'name_i18n' => ['en' => 'Banana', 'ar' => 'موز'], 'barcode' => null, 'kcal' => 89,
        ]);
    }

    public function test_search_matches_an_english_name_substring(): void
    {
        $this->seedFoods();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/foods?q=rice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'White Rice');
    }

    public function test_search_matches_an_arabic_name_substring(): void
    {
        $this->seedFoods();
        Sanctum::actingAs(Person::factory()->create());

        // Arabic-name search works here because food_items carry name_i18n.
        $this->getJson('/v1/foods?q='.urlencode('موز'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Banana');
    }

    public function test_search_localizes_name_by_accept_language(): void
    {
        $this->seedFoods();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/foods?q=rice', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'أرز أبيض');
    }

    public function test_barcode_lookup_returns_the_food(): void
    {
        $this->seedFoods();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/foods/barcode/1111111111')
            ->assertOk()
            ->assertJsonPath('data.name', 'Chicken Breast')
            ->assertJsonPath('data.kcal', 165);
    }

    public function test_barcode_lookup_unknown_returns_404(): void
    {
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/foods/barcode/0000000000')->assertNotFound();
    }

    public function test_food_search_requires_authentication(): void
    {
        $this->getJson('/v1/foods?q=rice')->assertUnauthorized();
    }
}
