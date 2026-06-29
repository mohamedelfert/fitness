<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Biometrics\Models\Biometric;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Progress analysis (FR-AN-001) — `GET /v1/me/progress`. Per-metric trend over a window plus a
 * goal projection driven by the goal's UNIT (kg/lb→weight, %→body_fat). reps/time/distance goals
 * have no biometric series — those are performance trends (FR-AN-003), out of this epic. Computed
 * on read (cheap person-scoped point queries), person-scoped throughout.
 */
class ProgressTest extends TestCase
{
    use RefreshDatabase;

    private function weighIn(Person $person, float $value, int $daysAgo): void
    {
        Biometric::create([
            'person_id' => $person->id,
            'type' => 'weight',
            'value' => $value,
            'unit' => 'kg',
            'measured_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/me/progress')->assertUnauthorized();
    }

    public function test_returns_metric_trend(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->weighIn($person, 80.0, 28);
        $this->weighIn($person, 78.0, 14);
        $this->weighIn($person, 76.0, 0);

        // Numeric fields compared loosely: whole-number floats serialize as JSON ints (80.0→80).
        $metric = $this->getJson('/v1/me/progress')->assertOk()->json('data.metrics.0');
        $this->assertSame('weight', $metric['type']);
        $this->assertEquals(80.0, $metric['first']);
        $this->assertEquals(76.0, $metric['latest']);
        $this->assertEquals(-4.0, $metric['change']);
        $this->assertSame(3, $metric['samples']);
        $this->assertLessThan(0, $metric['trend_per_week']);
    }

    public function test_projects_goal_toward_target_by_unit(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->weighIn($person, 80.0, 28);
        $this->weighIn($person, 76.0, 0); // -4kg/4wk → -1kg/wk
        Goal::factory()->for($person)->create([
            'type' => 'fat_loss', 'target_value' => 72.0, 'target_unit' => 'kg',
            'target_date' => now()->addDays(60)->toDateString(), 'status' => 'active',
        ]);

        $resp = $this->getJson('/v1/me/progress')->assertOk();
        $resp->assertJsonPath('data.goals.0.projection.status', 'projected')
            ->assertJsonPath('data.goals.0.projection.metric', 'weight')
            ->assertJsonPath('data.goals.0.projection.on_track', true);
        // 4kg to lose at 1kg/wk ≈ 28 days out, well inside the 60-day target.
        $this->assertNotNull($resp->json('data.goals.0.projection.projected_date'));
    }

    public function test_goal_trending_away_from_target_is_unfavorable(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->weighIn($person, 76.0, 28);
        $this->weighIn($person, 80.0, 0); // gaining, but goal is to lose
        Goal::factory()->for($person)->create([
            'type' => 'fat_loss', 'target_value' => 72.0, 'target_unit' => 'kg',
            'target_date' => now()->addDays(60)->toDateString(), 'status' => 'active',
        ]);

        $this->getJson('/v1/me/progress')->assertOk()
            ->assertJsonPath('data.goals.0.projection.status', 'trend_unfavorable')
            ->assertJsonPath('data.goals.0.projection.projected_date', null)
            ->assertJsonPath('data.goals.0.projection.on_track', false);
    }

    public function test_goal_with_non_biometric_unit_has_no_matching_metric(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->weighIn($person, 80.0, 14);
        Goal::factory()->for($person)->create([
            'type' => 'strength', 'target_value' => 100, 'target_unit' => 'reps', 'status' => 'active',
        ]);

        $this->getJson('/v1/me/progress')->assertOk()
            ->assertJsonPath('data.goals.0.projection.status', 'no_matching_metric')
            ->assertJsonPath('data.goals.0.projection.projected_date', null);
    }

    public function test_only_active_goals_and_own_data(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->weighIn($other, 99.0, 1); // other person's data must not leak
        Goal::factory()->for($person)->create(['status' => 'achieved', 'target_unit' => 'kg']);

        $resp = $this->getJson('/v1/me/progress')->assertOk();
        $this->assertCount(0, $resp->json('data.metrics')); // no own biometrics
        $this->assertCount(0, $resp->json('data.goals'));    // achieved goal excluded
    }
}
