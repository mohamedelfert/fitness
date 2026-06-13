<?php

namespace Modules\Training\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;

class WorkoutFactory extends Factory
{
    protected $model = Workout::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'day_index' => 1,
            'name' => 'Day 1',
            'ordering' => 1,
        ];
    }
}
