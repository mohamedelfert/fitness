<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\PlanAdjustmentGenerator;
use Modules\Training\Models\Exercise;
use Modules\Training\Models\Program;

/**
 * AI plan-adjustment endpoint (FR-AI-006). Same preconditions as the other AI generators — the
 * `ai-plan.generate` Gate (403), completed onboarding (422), and a funded AICredit wallet (402).
 * The target program is person-owned, so an unknown/cross-person id is hidden as 404 (not 403,
 * API_SPECIFICATION §13). Returns safe proposals (200, nothing persisted); the wallet is debited
 * once on success. Generation + safety live in the generator.
 */
class PlanAdjustmentController extends Controller
{
    public function store(Request $request, PlanAdjustmentGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete onboarding before requesting plan adjustments.',
            ]);
        }

        $data = $request->validate([
            'program_id' => ['required', 'string'],
            'goal' => ['sometimes', 'string', 'max:500'],
        ]);

        // Scoped lookup → 404 (not 403) when the program isn't this Person's.
        $program = Program::where('person_id', $person->id)->findOrFail($data['program_id']);

        $cost = $meter->costFor('plan_adjustment');
        $meter->ensureCanAfford($person, $cost);

        $adjustments = $generator->generate($person, $program, $data['goal'] ?? null);

        $meter->debit($person, $cost, 'plan_adjustment', $program);

        return response()->json(['data' => $this->present($program, $adjustments)]);
    }

    /**
     * @param  Collection<int, array{exercise: Exercise, adjustment: array<string, mixed>}>  $adjustments
     * @return array<string, mixed>
     */
    private function present(Program $program, Collection $adjustments): array
    {
        return [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'adjustments' => $adjustments->map(fn (array $a) => [
                'exercise_id' => $a['exercise']->id,
                'slug' => $a['exercise']->slug,
                'name' => $a['exercise']->name,
                'action' => $a['adjustment']['action'] ?? null,
                'replaces_slug' => $a['adjustment']['replaces_slug'] ?? null,
                'target_sets' => $a['adjustment']['target_sets'] ?? null,
                'target_reps' => isset($a['adjustment']['target_reps']) ? (string) $a['adjustment']['target_reps'] : null,
                'rationale' => isset($a['adjustment']['rationale']) ? (string) $a['adjustment']['rationale'] : null,
            ])->all(),
        ];
    }
}
