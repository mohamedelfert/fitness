<?php

namespace Modules\Nutrition\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Nutrition\Models\FoodItem;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;

class MealPlanItemFactory extends Factory
{
    protected $model = MealPlanItem::class;

    public function definition(): array
    {
        return [
            'meal_plan_day_id' => MealPlanDay::factory(),
            'meal_type' => 'breakfast',
            'food_item_id' => FoodItem::factory(),
            'servings' => 1,
            'ordering' => 1,
        ];
    }
}
