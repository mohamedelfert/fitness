<?php

namespace Modules\Identity\Support;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The onboarding training profile (FR-IDN, J1 step 2) — the structured capture that,
 * together with the Person's goals and PAR-Q+ status, becomes the AI input contract.
 * Vocabulary lives here; extend GLOSSARY.md before adding values. Injuries are captured
 * for contraindication gating on generated plans (FR-AI-007).
 */
final class OnboardingProfile
{
    public const EXPERIENCE_LEVELS = ['beginner', 'intermediate', 'advanced'];

    public const EQUIPMENT = [
        'none', 'bodyweight', 'dumbbells', 'barbell', 'kettlebell', 'bands', 'machines', 'full_gym',
    ];

    public const DIETARY_PREFERENCES = [
        'none', 'halal', 'vegetarian', 'vegan', 'pescatarian', 'keto', 'low_carb', 'mediterranean',
    ];

    /** The keys this profile owns (used to extract them from a validated request). */
    public const FIELDS = [
        'experience_level', 'equipment', 'training_days_per_week',
        'dietary_preferences', 'dietary_restrictions', 'injuries',
    ];

    /**
     * Validation rules for the training-profile fields.
     *
     * @param  bool  $required  true at onboarding (core fields mandatory); false for PATCH (all optional).
     * @return array<string, mixed>
     */
    public static function rules(bool $required = false): array
    {
        $core = $required ? 'required' : 'sometimes';

        return [
            'experience_level' => [$core, Rule::in(self::EXPERIENCE_LEVELS)],
            'equipment' => [$core, 'array'],
            'equipment.*' => [Rule::in(self::EQUIPMENT)],
            'training_days_per_week' => [$core, 'integer', 'min:0', 'max:14'],
            'dietary_preferences' => ['sometimes', 'array'],
            'dietary_preferences.*' => [Rule::in(self::DIETARY_PREFERENCES)],
            'dietary_restrictions' => ['sometimes', 'array'],
            'dietary_restrictions.*' => ['string', 'max:50'],
            'injuries' => ['sometimes', 'array'],
            'injuries.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Pick only the training-profile keys present in a validated payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function extract(array $validated): array
    {
        return Arr::only($validated, self::FIELDS);
    }
}
