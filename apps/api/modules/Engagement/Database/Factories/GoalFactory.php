<?php

namespace Modules\Engagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'type' => fake()->randomElement(Goal::TYPES),
            'target_value' => null,
            'target_unit' => null,
            'target_date' => null,
            'status' => 'active',
        ];
    }
}
