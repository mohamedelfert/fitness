<?php

namespace Modules\Nutrition\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Nutrition\Models\FoodItem;

class FoodItemFactory extends Factory
{
    protected $model = FoodItem::class;

    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'source' => 'seed',
            'name_i18n' => ['en' => $name],
            'barcode' => null,
            'serving_units' => [['label' => '100g', 'grams' => 100]],
            'kcal' => fake()->numberBetween(50, 400),
            'protein' => fake()->numberBetween(0, 40),
            'carbs' => fake()->numberBetween(0, 60),
            'fat' => fake()->numberBetween(0, 30),
        ];
    }
}
