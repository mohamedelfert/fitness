<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Models\Exercise;

/**
 * Exercise library browse + search (FR-TRN-001/006). Read-only shared asset; cursor-paginated,
 * filterable by muscle/equipment, instructions localized via Accept-Language. DB-backed search
 * (Exercise::scopeSearch) — swap that one scope for Meilisearch in production.
 */
class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']);
        $limit = min(max((int) $request->query('limit', 25), 1), 100);

        $page = Exercise::query()
            ->when($request->query('q'), fn ($q, $term) => $q->search($term))
            ->when($request->query('muscle'), fn ($q, $muscle) => $q->forMuscle($muscle))
            ->when($request->query('equipment'), fn ($q, $equipment) => $q->forEquipment($equipment))
            ->orderBy('name')
            ->cursorPaginate($limit);

        return response()->json([
            'data' => collect($page->items())->map(fn (Exercise $e) => $this->shape($e, $locale)),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']);

        return response()->json(['data' => $this->shape($exercise, $locale)]);
    }

    /** @return array<string, mixed> */
    private function shape(Exercise $e, string $locale): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'slug' => $e->slug,
            'primary_muscles' => $e->primary_muscles ?? [],
            'secondary_muscles' => $e->secondary_muscles ?? [],
            'equipment' => $e->equipment ?? [],
            'mechanics' => $e->mechanics,
            'instructions' => $e->localizedInstructions($locale),
            'media_keys' => $e->media_keys ?? [],
            'contraindications' => $e->contraindications ?? [],
        ];
    }
}
