<?php

namespace Modules\Nutrition\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;

/**
 * Meal plan read model (FR-AI-002). Person-scoped: cross-person access is hidden as 404,
 * never 403 (API_SPECIFICATION §13). AI generation (E1.6) / coach (P2) author these.
 */
class MealPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = MealPlan::where('person_id', $request->user()->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (MealPlan $p) => $this->summary($p));

        return response()->json(['data' => $plans]);
    }

    public function show(Request $request, string $mealPlan): JsonResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']);

        $model = MealPlan::where('person_id', $request->user()->id)
            ->with(['days.items.foodItem'])
            ->findOrFail($mealPlan);

        return response()->json(['data' => [
            ...$this->summary($model),
            'daily_targets' => $model->daily_targets_json,
            'days' => $model->days->map(fn (MealPlanDay $d) => [
                'id' => $d->id,
                'day_index' => $d->day_index,
                'name' => $d->name,
                'ordering' => $d->ordering,
                'items' => $d->items->map(fn (MealPlanItem $i) => [
                    'id' => $i->id,
                    'meal_type' => $i->meal_type,
                    'food_item_id' => $i->food_item_id,
                    'food_name' => $i->foodItem?->localizedName($locale),
                    'recipe_id' => $i->recipe_id,
                    'servings' => $i->servings,
                    'target_kcal' => $i->target_kcal,
                    'target_macros' => $i->target_macros_json,
                    'ordering' => $i->ordering,
                    'notes' => $i->notes,
                ])->all(),
            ])->all(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(MealPlan $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'source' => $p->source,
            'status' => $p->status,
            'start_date' => $p->start_date?->toDateString(),
        ];
    }
}
