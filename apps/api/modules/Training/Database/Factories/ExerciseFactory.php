<?php

namespace Modules\Training\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Training\Models\Exercise;

class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'primary_muscles' => ['chest'],
            'equipment' => ['barbell'],
            'instructions' => ['en' => 'Perform with control.'],
            'contraindications' => [],
        ];
    }
}
