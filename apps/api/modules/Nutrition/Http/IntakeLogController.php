<?php

namespace Modules\Nutrition\Http;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Nutrition\Models\SupplementLog;
use Modules\Nutrition\Models\WaterLog;

/**
 * Water (FR-NUT-006) and supplement (FR-NUT-007) intake logging. Append-only & idempotent
 * on client_ulid (offline sync, ADR-005); person-scoped.
 */
class IntakeLogController extends Controller
{
    public function water(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_ml' => ['required', 'integer', 'min:1'],
            'client_ulid' => ['nullable', 'string'],
            'logged_at' => ['nullable', 'date'],
        ]);

        return $this->append($request, WaterLog::class, $validated, fn (WaterLog $w) => [
            'id' => $w->id,
            'amount_ml' => $w->amount_ml,
            'logged_at' => $w->logged_at?->toIso8601String(),
        ]);
    }

    public function supplement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:16'],
            'client_ulid' => ['nullable', 'string'],
            'logged_at' => ['nullable', 'date'],
        ]);

        return $this->append($request, SupplementLog::class, $validated, fn (SupplementLog $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'dose' => $s->dose,
            'unit' => $s->unit,
            'logged_at' => $s->logged_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $validated
     * @param  callable(Model): array<string, mixed>  $shape
     */
    private function append(Request $request, string $model, array $validated, callable $shape): JsonResponse
    {
        if (! empty($validated['client_ulid'])) {
            $existing = $model::where('person_id', $request->user()->id)
                ->where('client_ulid', $validated['client_ulid'])
                ->first();

            if ($existing) {
                return response()->json(['data' => $shape($existing)], 200);
            }
        }

        $log = $model::create([
            'person_id' => $request->user()->id,
            'logged_at' => $validated['logged_at'] ?? now(),
            ...$validated,
        ]);

        return response()->json(['data' => $shape($log)], 201);
    }
}
