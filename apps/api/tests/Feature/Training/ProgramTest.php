<?php

namespace Tests\Feature\Training;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Program;
use Modules\Training\Models\Workout;
use Modules\Training\Models\WorkoutExercise;
use Tests\TestCase;

/**
 * Program read model (FR-TRN-005, FR-AI-001): programs → workouts → workout_exercises.
 * Person-owned (Plane A). The structure AI generation (E1.6) and the Today loop build on.
 */
class ProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_authenticated_persons_programs(): void
    {
        $me = Person::factory()->create();
        $other = Person::factory()->create();
        Program::factory()->for($me, 'person')->create(['name' => 'My Program']);
        Program::factory()->for($other, 'person')->create(['name' => 'Their Program']);

        Sanctum::actingAs($me);

        $this->getJson('/v1/programs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Program');
    }

    public function test_program_detail_nests_workouts_and_their_exercises(): void
    {
        $me = Person::factory()->create();
        $program = Program::factory()->for($me, 'person')->create(['name' => 'Hypertrophy Block']);
        $push = Workout::factory()->for($program)->create(['day_index' => 1, 'name' => 'Push', 'ordering' => 1]);
        $pull = Workout::factory()->for($program)->create(['day_index' => 2, 'name' => 'Pull', 'ordering' => 2]);
        $bench = Exercise::factory()->create(['name' => 'Barbell Bench Press']);
        WorkoutExercise::factory()->for($push)->create([
            'exercise_id' => $bench->id, 'order' => 1, 'target_sets' => 4, 'target_reps' => '6-8',
        ]);

        Sanctum::actingAs($me);

        $this->getJson("/v1/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Hypertrophy Block')
            ->assertJsonCount(2, 'data.workouts')
            ->assertJsonPath('data.workouts.0.name', 'Push')
            ->assertJsonPath('data.workouts.1.name', 'Pull')
            ->assertJsonPath('data.workouts.0.exercises.0.exercise_name', 'Barbell Bench Press')
            ->assertJsonPath('data.workouts.0.exercises.0.target_sets', 4)
            ->assertJsonPath('data.workouts.0.exercises.0.target_reps', '6-8');
    }

    public function test_cannot_view_another_persons_program(): void
    {
        $program = Program::factory()->create(); // belongs to some other Person
        Sanctum::actingAs(Person::factory()->create());

        // Cross-person access is hidden as 404, never 403 (API_SPECIFICATION §13).
        $this->getJson("/v1/programs/{$program->id}")->assertNotFound();
    }

    public function test_programs_require_authentication(): void
    {
        $this->getJson('/v1/programs')->assertUnauthorized();
    }
}
