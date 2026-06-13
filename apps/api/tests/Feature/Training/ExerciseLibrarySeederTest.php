<?php

namespace Tests\Feature\Training;

use Database\Seeders\ExerciseLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Training\Models\Exercise;
use Tests\TestCase;

/**
 * The dev exercise-library seed (full licensed dataset is the deferred Q4 dependency).
 * Verifies the starter set is bilingual and carries contraindications that feed FR-AI-007.
 */
class ExerciseLibrarySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_a_bilingual_library_with_contraindications(): void
    {
        $this->seed(ExerciseLibrarySeeder::class);

        $this->assertGreaterThanOrEqual(8, Exercise::count());

        $squat = Exercise::where('slug', 'back-squat')->first();
        $this->assertNotNull($squat);
        $this->assertArrayHasKey('ar', $squat->instructions);
        $this->assertArrayHasKey('en', $squat->instructions);

        $bench = Exercise::where('slug', 'barbell-bench-press')->first();
        $this->assertNotEmpty($bench->contraindications);
    }

    public function test_default_database_seeder_seeds_the_library(): void
    {
        $this->seed(); // runs Database\Seeders\DatabaseSeeder

        $this->assertGreaterThan(0, Exercise::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ExerciseLibrarySeeder::class);
        $count = Exercise::count();
        $this->seed(ExerciseLibrarySeeder::class);

        $this->assertSame($count, Exercise::count());
    }
}
