<?php

namespace Tests\Feature\Training;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Tests\TestCase;

/**
 * Exercise library browse + search (FR-TRN-001/006). Read-only shared asset (Plane A),
 * cursor-paginated, filterable by muscle/equipment, localized instructions via
 * Accept-Language. DB-backed search for now (Meilisearch is the production path).
 */
class ExerciseLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function seedLibrary(): void
    {
        Exercise::factory()->create([
            'name' => 'Barbell Bench Press', 'slug' => 'barbell-bench-press',
            'primary_muscles' => ['chest'], 'equipment' => ['barbell'],
            'instructions' => ['en' => 'Lower the bar to your chest.', 'ar' => 'أنزل البار إلى صدرك.'],
            'contraindications' => ['shoulder_impingement'],
        ]);
        Exercise::factory()->create([
            'name' => 'Back Squat', 'slug' => 'back-squat',
            'primary_muscles' => ['quads'], 'equipment' => ['barbell'],
            'instructions' => ['en' => 'Descend to depth.', 'ar' => 'انزل لعمق كامل.'],
        ]);
        Exercise::factory()->create([
            'name' => 'Push-up', 'slug' => 'push-up',
            'primary_muscles' => ['chest'], 'equipment' => ['bodyweight'],
            'instructions' => ['en' => 'Keep a straight line.'],
        ]);
    }

    public function test_lists_the_library(): void
    {
        $this->seedLibrary();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/exercises')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'primary_muscles', 'equipment', 'instructions']], 'meta' => ['next_cursor']]);
    }

    public function test_search_matches_a_name_substring(): void
    {
        $this->seedLibrary();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/exercises?q=squat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Back Squat');
    }

    public function test_filters_by_primary_muscle(): void
    {
        $this->seedLibrary();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/exercises?muscle=chest')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_equipment(): void
    {
        $this->seedLibrary();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson('/v1/exercises?equipment=bodyweight')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Push-up');
    }

    public function test_arabic_query_does_not_error(): void
    {
        $this->seedLibrary();
        Sanctum::actingAs(Person::factory()->create());

        // Robustness: an Arabic query must not break (names are canonical; localized-name
        // search is a deliberate future decision, not invented here).
        $this->getJson('/v1/exercises?q='.urlencode('القرفصاء'))->assertOk();
    }

    public function test_instructions_are_localized_by_accept_language(): void
    {
        $this->seedLibrary();
        $exercise = Exercise::where('slug', 'barbell-bench-press')->firstOrFail();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson("/v1/exercises/{$exercise->id}", ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.instructions', 'أنزل البار إلى صدرك.');

        $this->getJson("/v1/exercises/{$exercise->id}", ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.instructions', 'Lower the bar to your chest.');
    }

    public function test_show_returns_contraindications_for_the_safety_gate(): void
    {
        $this->seedLibrary();
        $exercise = Exercise::where('slug', 'barbell-bench-press')->firstOrFail();
        Sanctum::actingAs(Person::factory()->create());

        $this->getJson("/v1/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.contraindications', ['shoulder_impingement']);
    }

    public function test_library_requires_authentication(): void
    {
        $this->getJson('/v1/exercises')->assertUnauthorized();
    }
}
