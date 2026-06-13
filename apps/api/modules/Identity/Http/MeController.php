<?php

namespace Modules\Identity\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\OnboardingProfile;

/**
 * The current Person's profile (FR-IDN-002). GET/PATCH /v1/me — API_SPECIFICATION §2.
 * Returns the demographic basics plus the onboarding training profile; PATCH updates
 * either (all fields optional) and validates against the profile vocabulary.
 */
class MeController extends Controller
{
    private const BASIC_FIELDS = [
        'display_name', 'sex', 'dob', 'height_cm', 'locale', 'unit_system', 'timezone', 'country',
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->shape($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->basicRules(),
            ...OnboardingProfile::rules(required: false),
        ]);

        $person = $request->user();

        $basics = Arr::only($validated, self::BASIC_FIELDS);
        if ($basics !== []) {
            $person->fill($basics);
        }

        $training = OnboardingProfile::extract($validated);
        if ($training !== []) {
            $person->mergeTrainingProfile($training);
        }

        $person->save();

        return response()->json(['data' => $this->shape($person->refresh())]);
    }

    /** @return array<string, mixed> */
    private function basicRules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:120'],
            'sex' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'dob' => ['sometimes', 'nullable', 'date'],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:50', 'max:300'],
            'locale' => ['sometimes', Rule::in(['en', 'ar'])],
            'unit_system' => ['sometimes', Rule::in(['metric', 'imperial'])],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }

    /** @return array<string, mixed> */
    private function shape(Person $p): array
    {
        return [
            'id' => $p->id,
            'display_name' => $p->display_name,
            'email' => $p->email,
            'phone' => $p->phone,
            'sex' => $p->sex,
            'dob' => $p->dob?->toDateString(),
            'height_cm' => $p->height_cm,
            'locale' => $p->locale,
            'unit_system' => $p->unit_system,
            'timezone' => $p->timezone,
            'country' => $p->country,
            'health_screen_status' => $p->health_screen_status,
            'training_profile' => $p->trainingProfile(),
            'onboarding_completed' => $p->isOnboardingComplete(),
        ];
    }
}
