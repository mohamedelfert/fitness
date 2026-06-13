<?php

namespace Modules\Identity\Support;

use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;

/**
 * Assembles the AI input contract the Brain consumes (E1.1 → E1.6): goal, experience,
 * equipment, schedule, diet, injuries, demographics, and PAR-Q+ status. `ready_for_ai`
 * is the single readiness signal — true only when the Person is health-screen-cleared
 * AND has completed onboarding. Injuries + screen status drive contraindication gating
 * (FR-AI-007 / NFR-AI-002 / INV-005).
 */
final class AiInputProfile
{
    /** @return array<string, mixed> */
    public static function for(Person $person): array
    {
        $profile = $person->trainingProfile();

        $goals = Goal::where('person_id', $person->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (Goal $g) => [
                'type' => $g->type,
                'target_value' => $g->target_value,
                'target_unit' => $g->target_unit,
                'target_date' => $g->target_date?->toDateString(),
            ])->all();

        return [
            'goals' => $goals,
            'experience_level' => $profile['experience_level'] ?? null,
            'equipment' => $profile['equipment'] ?? [],
            'training_days_per_week' => $profile['training_days_per_week'] ?? null,
            'dietary_preferences' => $profile['dietary_preferences'] ?? [],
            'dietary_restrictions' => $profile['dietary_restrictions'] ?? [],
            'injuries' => $profile['injuries'] ?? [],
            'demographics' => [
                'sex' => $person->sex,
                'dob' => $person->dob?->toDateString(),
                'height_cm' => $person->height_cm,
            ],
            'locale' => $person->locale,
            'unit_system' => $person->unit_system,
            'health_screen_status' => $person->health_screen_status,
            'ready_for_ai' => $person->health_screen_status === 'passed' && $person->isOnboardingComplete(),
        ];
    }
}
