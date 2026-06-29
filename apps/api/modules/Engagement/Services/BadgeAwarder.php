<?php

namespace Modules\Engagement\Services;

use Modules\Engagement\Models\PersonBadge;
use Modules\Identity\Models\Person;

/**
 * Awards badges (FR-ENG-003) from the current gamification stats and returns the Person's earned
 * set. A badge is a historical award: earned once, persisted in person_badges, never recomputed —
 * so it survives any later change in the underlying stat. Catalog is config (`gamification.badges`).
 */
class BadgeAwarder
{
    /**
     * Persist any newly-earned badges, then return all of the Person's badges (oldest first).
     *
     * @param  array<string, mixed>  $stats  gamification stats (xp / level / streak_days / …)
     * @return list<array<string, mixed>>
     */
    public function sync(Person $person, array $stats): array
    {
        $catalog = collect(config('gamification.badges', []));

        foreach ($catalog as $badge) {
            $value = $stats[$badge['stat']] ?? null;
            if ($value !== null && $value >= $badge['gte']) {
                // ponytail: awarded on read → earned_at is first-observation, not the exact cross.
                // Move to event-driven awarding only if exact earn-time matters (e.g. a push).
                PersonBadge::firstOrCreate(
                    ['person_id' => $person->id, 'badge_slug' => $badge['slug']],
                    ['earned_at' => now()],
                );
            }
        }

        $names = $catalog->keyBy('slug');

        return PersonBadge::where('person_id', $person->id)
            ->orderBy('earned_at')->get()
            ->map(fn (PersonBadge $b) => [
                'slug' => $b->badge_slug,
                'name' => $names[$b->badge_slug]['name'] ?? $b->badge_slug,
                'earned_at' => $b->earned_at?->toIso8601String(),
            ])->all();
    }
}
