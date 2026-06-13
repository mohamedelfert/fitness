<?php

namespace Modules\Nutrition\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Nutrition\Models\FoodItem;
use Modules\Nutrition\Models\FoodLog;
use Modules\Nutrition\Models\WaterLog;

/**
 * Append a FoodLog (FR-NUT-002) and roll up the day's macros. Append-only & idempotent
 * (client_ulid, ADR-005). Logging from a FoodItem snapshots servings × per-serving macros.
 */
class FoodLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'food_item_id' => ['nullable', 'string', 'exists:food_items,id'],
            'recipe_id' => ['nullable', 'string'],
            'meal_type' => ['required', Rule::in(FoodLog::MEAL_TYPES)],
            'servings' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', Rule::in(FoodLog::SOURCES)],
            'client_ulid' => ['nullable', 'string'],
            // Custom entries (no food_item_id) must carry their own calories.
            'kcal' => [Rule::requiredIf(fn () => empty($request->input('food_item_id'))), 'numeric', 'min:0'],
            'protein' => ['nullable', 'numeric', 'min:0'],
            'carbs' => ['nullable', 'numeric', 'min:0'],
            'fat' => ['nullable', 'numeric', 'min:0'],
            'logged_at' => ['nullable', 'date'],
        ]);

        // Idempotent offline sync: a retried mutation with the same client_ulid returns the original.
        if (! empty($validated['client_ulid'])) {
            $existing = FoodLog::where('person_id', $request->user()->id)
                ->where('client_ulid', $validated['client_ulid'])
                ->first();

            if ($existing) {
                return response()->json(['data' => $this->shape($existing)], 200);
            }
        }

        $servings = (float) ($validated['servings'] ?? 1);
        $macros = $this->resolveMacros($validated, $servings);

        $log = FoodLog::create([
            'person_id' => $request->user()->id,
            'food_item_id' => $validated['food_item_id'] ?? null,
            'recipe_id' => $validated['recipe_id'] ?? null,
            'meal_type' => $validated['meal_type'],
            'servings' => $servings,
            ...$macros,
            'source' => $validated['source'] ?? (isset($validated['food_item_id']) ? 'search' : 'custom'),
            'client_ulid' => $validated['client_ulid'] ?? null,
            'logged_at' => $validated['logged_at'] ?? now(),
        ]);

        return response()->json(['data' => $this->shape($log)], 201);
    }

    /** GET /v1/me/nutrition/summary?date= — daily macro/calorie rollup (FR-NUT-002). */
    public function summary(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $base = FoodLog::where('person_id', $request->user()->id)->whereDate('logged_at', $date);
        $sums = (clone $base)->selectRaw(
            'COALESCE(SUM(kcal),0) kcal, COALESCE(SUM(protein),0) protein, COALESCE(SUM(carbs),0) carbs, COALESCE(SUM(fat),0) fat'
        )->first();

        $waterMl = (int) WaterLog::where('person_id', $request->user()->id)
            ->whereDate('logged_at', $date)->sum('amount_ml');

        return response()->json(['data' => [
            'date' => $date,
            'kcal' => (float) $sums->kcal,
            'protein' => (float) $sums->protein,
            'carbs' => (float) $sums->carbs,
            'fat' => (float) $sums->fat,
            'water_ml' => $waterMl,
            'entries' => (clone $base)->count(),
        ]]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{kcal: float, protein: float, carbs: float, fat: float}
     */
    private function resolveMacros(array $validated, float $servings): array
    {
        if (! empty($validated['food_item_id'])) {
            $item = FoodItem::findOrFail($validated['food_item_id']);

            return [
                'kcal' => round($item->kcal * $servings, 2),
                'protein' => round($item->protein * $servings, 2),
                'carbs' => round($item->carbs * $servings, 2),
                'fat' => round($item->fat * $servings, 2),
            ];
        }

        return [
            'kcal' => (float) $validated['kcal'],
            'protein' => (float) ($validated['protein'] ?? 0),
            'carbs' => (float) ($validated['carbs'] ?? 0),
            'fat' => (float) ($validated['fat'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function shape(FoodLog $log): array
    {
        return [
            'id' => $log->id,
            'food_item_id' => $log->food_item_id,
            'meal_type' => $log->meal_type,
            'servings' => $log->servings,
            'kcal' => $log->kcal,
            'protein' => $log->protein,
            'carbs' => $log->carbs,
            'fat' => $log->fat,
            'source' => $log->source,
            'logged_at' => $log->logged_at?->toIso8601String(),
        ];
    }
}
