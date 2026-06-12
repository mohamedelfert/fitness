<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\Person;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => Hash::make('password'),
            'locale' => 'en',
            'unit_system' => 'metric',
            'timezone' => 'UTC',
            'health_screen_status' => 'passed',
        ];
    }
}
