<?php

namespace Modules\Training\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;

class WorkoutExerciseFactory extends Factory
{
    protected $model = WorkoutExercise::class;

    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_id' => Exercise::factory(),
            'order' => 1,
            'target_sets' => 3,
            'target_reps' => '8-12',
            'target_load' => null,
            'rest_sec' => 90,
            'tempo' => null,
            'notes' => null,
        ];
    }
}
