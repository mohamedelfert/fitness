<?php

namespace Tests\Feature\Engagement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Engagement\Models\PersonBadge;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Session;
use Tests\TestCase;

/**
 * Badges (FR-ENG-003) — a *historical award*, so it is PERSISTED in `person_badges`, never
 * recomputed: once earned it stays earned even if the underlying stat later changes. Awarded on
 * read at `GET /v1/me/gamification` (materialise-on-GET, like daily-rec), idempotent via the
 * unique (person_id, badge_slug). Catalog is config (`gamification.badges`).
 */
class BadgeTest extends TestCase
{
    use RefreshDatabase;

    private function sessionStreak(Person $person, int $days): void
    {
        for ($d = 0; $d < $days; $d++) {
            Session::create(['person_id' => $person->id, 'started_at' => now()->subDays($d)->setTime(12, 0)]);
        }
    }

    public function test_earns_a_badge_when_its_threshold_is_crossed(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->sessionStreak($person, 7); // 7-day streak → earns streak_7

        $badges = $this->getJson('/v1/me/gamification')->assertOk()->json('data.badges');

        $this->assertContains('streak_7', array_column($badges, 'slug'));
        $this->assertDatabaseHas('person_badges', ['person_id' => $person->id, 'badge_slug' => 'streak_7']);
    }

    public function test_no_badge_below_threshold(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->sessionStreak($person, 2);

        $this->getJson('/v1/me/gamification')->assertOk()->assertJsonPath('data.badges', []);
        $this->assertSame(0, PersonBadge::where('person_id', $person->id)->count());
    }

    public function test_badge_is_awarded_only_once(): void
    {
        $person = Person::factory()->create();
        Sanctum::actingAs($person);
        $this->sessionStreak($person, 7);

        $this->getJson('/v1/me/gamification')->assertOk();
        $this->getJson('/v1/me/gamification')->assertOk(); // second read must not duplicate

        $this->assertSame(1, PersonBadge::where('person_id', $person->id)->where('badge_slug', 'streak_7')->count());
    }

    public function test_badges_are_person_scoped(): void
    {
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        PersonBadge::create(['person_id' => $other->id, 'badge_slug' => 'streak_7', 'earned_at' => now()]);
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/gamification')->assertOk()->assertJsonPath('data.badges', []);
    }
}
