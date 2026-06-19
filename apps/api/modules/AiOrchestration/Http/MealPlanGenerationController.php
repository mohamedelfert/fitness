<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\MealPlanGenerator;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;

/**
 * AI meal-plan generation endpoint (FR-AI-002), the nutrition twin of ProgramGenerationController.
 * Same preconditions: the `ai-plan.generate` Gate (403, uniform across AI endpoints per the
 * phase plan), completed onboarding (422), and a funded AICredit wallet (402). The wallet is
 * debited ONCE after a plan persists — failed/rejected attempts (incl. the dietary regenerate
 * loop) are free. Generation + the dietary post-eval live in MealPlanGenerator.
 */
class MealPlanGenerationController extends Controller
{
    public function store(Request $request, MealPlanGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete onboarding before generating a meal plan.',
            ]);
        }

        $cost = $meter->costFor('meal_plan');
        $meter->ensureCanAfford($person, $cost);

        $mealPlan = $generator->generate($person);

        $meter->debit($person, $cost, 'meal_plan_generation', $mealPlan);

        $locale = $request->getPreferredLanguage(['en', 'ar']);

        return response()->json(['data' => $this->present($mealPlan, $locale)], 201);
    }

    /** @return array<string, mixed> */
    private function present(MealPlan $mealPlan, string $locale): array
    {
        return [
            'id' => $mealPlan->id,
            'name' => $mealPlan->name,
            'source' => $mealPlan->source,
            'status' => $mealPlan->status,
            'start_date' => $mealPlan->start_date?->toDateString(),
            'daily_targets' => $mealPlan->daily_targets_json,
            'days' => $mealPlan->days->map(fn (MealPlanDay $d) => [
                'id' => $d->id,
                'day_index' => $d->day_index,
                'name' => $d->name,
                'ordering' => $d->ordering,
                'items' => $d->items->map(fn (MealPlanItem $i) => [
                    'id' => $i->id,
                    'meal_type' => $i->meal_type,
                    'food_item_id' => $i->food_item_id,
                    'food_name' => $i->foodItem?->localizedName($locale),
                    'servings' => $i->servings,
                    'target_kcal' => $i->target_kcal,
                    'target_macros' => $i->target_macros_json,
                    'ordering' => $i->ordering,
                    'notes' => $i->notes,
                ])->all(),
            ])->all(),
        ];
    }
}
