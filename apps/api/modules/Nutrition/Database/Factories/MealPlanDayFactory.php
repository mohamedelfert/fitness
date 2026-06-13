<?php

namespace Modules\Nutrition\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;

class MealPlanDayFactory extends Factory
{
    protected $model = MealPlanDay::class;

    public function definition(): array
    {
        return [
            'meal_plan_id' => MealPlan::factory(),
            'day_index' => 1,
            'name' => 'Day 1',
            'ordering' => 1,
        ];
    }
}
