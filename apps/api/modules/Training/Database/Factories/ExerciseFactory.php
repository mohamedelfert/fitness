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
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'primary_muscles' => ['chest'],
            'secondary_muscles' => ['triceps'],
            'equipment' => ['barbell'],
            'mechanics' => 'compound',
            'instructions' => ['en' => 'Perform with control.'],
            'media_keys' => [],
            'contraindications' => [],
        ];
    }
}
