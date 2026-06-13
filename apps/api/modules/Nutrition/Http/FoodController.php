<?php

namespace Modules\Nutrition\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Nutrition\Models\FoodItem;

/**
 * Food database browse + barcode lookup (FR-NUT-001/003). Read-only; localized via
 * Accept-Language. DB-backed search (FoodItem::scopeSearch) — swap for Meilisearch in prod.
 */
class FoodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']);
        $limit = min(max((int) $request->query('limit', 25), 1), 100);

        $page = FoodItem::query()
            ->when($request->query('q'), fn ($q, $term) => $q->search($term))
            ->orderBy('id')
            ->cursorPaginate($limit);

        return response()->json([
            'data' => collect($page->items())->map(fn (FoodItem $f) => $this->shape($f, $locale)),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function barcode(Request $request, string $code): JsonResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']);
        $item = FoodItem::where('barcode', $code)->firstOrFail();

        return response()->json(['data' => $this->shape($item, $locale)]);
    }

    /** @return array<string, mixed> */
    private function shape(FoodItem $f, string $locale): array
    {
        return [
            'id' => $f->id,
            'name' => $f->localizedName($locale),
            'brand' => $f->brand,
            'barcode' => $f->barcode,
            'serving_units' => $f->serving_units ?? [],
            'kcal' => $f->kcal,
            'protein' => $f->protein,
            'carbs' => $f->carbs,
            'fat' => $f->fat,
        ];
    }
}
