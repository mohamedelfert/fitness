<?php

namespace Modules\Training\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Program;

class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'source' => 'ai',
            'name' => ucwords(fake()->words(2, true)).' Program',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
