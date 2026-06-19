<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\ExerciseAlternativeGenerator;
use Modules\Training\Models\Exercise;

/**
 * AI exercise-alternatives endpoint (FR-AI-003). Same preconditions as the other AI generators —
 * the `ai-plan.generate` Gate (403), completed onboarding (422), and a funded AICredit wallet
 * (402) — plus a valid source exercise to swap (422). Returns safe suggestions (200, nothing
 * persisted); the wallet is debited once on success. Generation + safety live in the generator.
 */
class ExerciseAlternativeController extends Controller
{
    public function store(Request $request, ExerciseAlternativeGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete onboarding before requesting exercise alternatives.',
            ]);
        }

        $data = $request->validate([
            'exercise_slug' => ['required', 'string'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);

        $source = Exercise::where('slug', $data['exercise_slug'])->first();
        if ($source === null) {
            throw ValidationException::withMessages(['exercise_slug' => 'Unknown exercise.']);
        }

        $cost = $meter->costFor('exercise_alternatives');
        $meter->ensureCanAfford($person, $cost);

        $alternatives = $generator->generate($person, $source, $data['count'] ?? 3);

        $meter->debit($person, $cost, 'exercise_alternatives', $source);

        return response()->json(['data' => $this->present($source, $alternatives)]);
    }

    /**
     * @param  Collection<int, array{exercise: Exercise, rationale: ?string}>  $alternatives
     * @return array<string, mixed>
     */
    private function present(Exercise $source, Collection $alternatives): array
    {
        return [
            'source' => ['id' => $source->id, 'slug' => $source->slug, 'name' => $source->name],
            'alternatives' => $alternatives->map(fn (array $a) => [
                'exercise_id' => $a['exercise']->id,
                'slug' => $a['exercise']->slug,
                'name' => $a['exercise']->name,
                'rationale' => $a['rationale'],
            ])->all(),
        ];
    }
}
