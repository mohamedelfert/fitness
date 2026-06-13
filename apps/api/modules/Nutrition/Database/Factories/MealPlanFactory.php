<?php

namespace Modules\Nutrition\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\MealPlan;

class MealPlanFactory extends Factory
{
    protected $model = MealPlan::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'source' => 'ai',
            'name' => ucwords(fake()->words(2, true)).' Plan',
            'daily_targets_json' => ['kcal' => 2200, 'protein' => 160, 'carbs' => 220, 'fat' => 70],
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
