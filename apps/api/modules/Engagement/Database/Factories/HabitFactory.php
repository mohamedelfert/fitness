<?php

namespace Modules\Engagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Engagement\Models\Habit;
use Modules\Identity\Models\Person;

class HabitFactory extends Factory
{
    protected $model = Habit::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'name' => fake()->randomElement(['Drink water', 'Stretch', '10k steps', 'Sleep by 11pm']),
            'cadence' => 'daily',
            'target_per_period' => 1,
            'active' => true,
        ];
    }
}
