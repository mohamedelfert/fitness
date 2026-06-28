<?php

namespace Modules\Wearables\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Wearables\Models\WearableStream;

/**
 * Wearable ingest + read-back (FR-BIO-003). Batch ingest (devices sync many samples at once),
 * append-only & person-scoped, idempotent per-reading on client_ulid (offline sync, ADR-005).
 * One SELECT + one bulk INSERT per batch keeps a high-write endpoint cheap.
 */
class WearableController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'readings' => ['required', 'array', 'min:1', 'max:1000'],
            'readings.*.metric' => ['required', Rule::in(WearableStream::METRICS)],
            'readings.*.value' => ['required', 'numeric'],
            'readings.*.recorded_at' => ['required', 'date'],
            'readings.*.source' => ['nullable', 'string', 'max:32'],
            'readings.*.client_ulid' => ['nullable', 'string', 'max:48'],
        ]);

        $personId = $request->user()->id;
        $readings = $validated['readings'];

        // Dedup against already-stored client_ulids (one query) AND within the batch itself.
        $batchUlids = array_values(array_filter(array_map(fn ($r) => $r['client_ulid'] ?? null, $readings)));
        $seen = $batchUlids === []
            ? []
            : array_flip(WearableStream::where('person_id', $personId)->whereIn('client_ulid', $batchUlids)->pluck('client_ulid')->all());

        $now = now()->toDateTimeString();
        $rows = [];
        $skipped = 0;

        foreach ($readings as $r) {
            $ulid = $r['client_ulid'] ?? null;
            if ($ulid !== null) {
                if (isset($seen[$ulid])) {
                    $skipped++;

                    continue;
                }
                $seen[$ulid] = true;
            }

            $rows[] = [
                'id' => (string) Str::ulid(),
                'person_id' => $personId,
                'source' => $r['source'] ?? null,
                'metric' => $r['metric'],
                'value' => $r['value'],
                'client_ulid' => $ulid,
                'recorded_at' => Carbon::parse($r['recorded_at'])->toDateTimeString(),
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            WearableStream::insert($rows);
        }

        return response()->json(['data' => ['ingested' => count($rows), 'skipped' => $skipped]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['metric' => ['sometimes', Rule::in(WearableStream::METRICS)]]);

        $streams = WearableStream::where('person_id', $request->user()->id)
            ->when($validated['metric'] ?? null, fn ($q, $metric) => $q->where('metric', $metric))
            ->orderByDesc('recorded_at')
            ->limit(200) // ponytail: capped; cursor-paginate when charts need deeper history
            ->get()
            ->map(fn (WearableStream $s) => [
                'id' => $s->id,
                'source' => $s->source,
                'metric' => $s->metric,
                'value' => $s->value,
                'recorded_at' => $s->recorded_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $streams]);
    }
}
