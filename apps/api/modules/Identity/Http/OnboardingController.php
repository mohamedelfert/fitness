<?php

namespace Modules\Identity\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Support\AiInputProfile;
use Modules\Identity\Support\OnboardingProfile;

/**
 * Onboarding profile capture (FR-IDN, J1 step 2). One multi-step submit: persists the
 * demographic basics, the training profile (incl. injuries for contraindication gating),
 * creates the Person's goal(s), and marks onboarding complete — leaving the Person ready
 * for first AI plan generation once health-screen-cleared (E1.6 handoff).
 */
class OnboardingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sex' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'dob' => ['sometimes', 'nullable', 'date'],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:50', 'max:300'],
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.type' => ['required', Rule::in(Goal::TYPES)],
            'goals.*.target_value' => ['nullable', 'numeric'],
            'goals.*.target_unit' => ['nullable', 'string', 'max:16'],
            'goals.*.target_date' => ['nullable', 'date'],
            ...OnboardingProfile::rules(required: true),
        ]);

        $person = $request->user();

        DB::transaction(function () use ($person, $validated) {
            $basics = Arr::only($validated, ['sex', 'dob', 'height_cm']);
            if ($basics !== []) {
                $person->fill($basics);
            }
            $person->mergeTrainingProfile(OnboardingProfile::extract($validated));
            $person->markOnboardingComplete();
            $person->save();

            // Idempotent on re-submit (network retry / edit): a Person has at most one
            // active goal per type; re-onboarding updates its targets rather than duplicating.
            foreach ($validated['goals'] as $goal) {
                Goal::updateOrCreate(
                    ['person_id' => $person->id, 'type' => $goal['type'], 'status' => 'active'],
                    Arr::only($goal, ['target_value', 'target_unit', 'target_date']),
                );
            }
        });

        return response()->json(['data' => [
            'onboarding_completed' => true,
            'ai_input_profile' => AiInputProfile::for($person->refresh()),
        ]], 201);
    }
}
