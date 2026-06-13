<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Default dev seed. The platform super-admin is seeded separately (PlatformAdminSeeder,
 * run explicitly in Docker/CI — see SESSION_HANDOFF §5) so it isn't created in every env.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ExerciseLibrarySeeder::class);
    }
}
