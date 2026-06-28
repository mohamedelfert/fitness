<?php

namespace Modules\Biometrics\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Biometrics\Models\Biometric;

/**
 * Biometrics logging + read-back (FR-BIO-001). Append-only & idempotent on client_ulid
 * (offline sync, ADR-005); strictly person-scoped (Plane A, Person-owned data).
 */
class BiometricController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(Biometric::TYPES)],
            'value' => ['required', 'numeric'],
            'unit' => ['required', 'string', 'max:16'],
            'client_ulid' => ['nullable', 'string', 'max:48'],
            'measured_at' => ['nullable', 'date'],
        ]);

        if (! empty($validated['client_ulid'])) {
            $existing = Biometric::where('person_id', $request->user()->id)
                ->where('client_ulid', $validated['client_ulid'])
                ->first();

            if ($existing) {
                return response()->json(['data' => $this->shape($existing)], 200);
            }
        }

        $biometric = Biometric::create([
            'person_id' => $request->user()->id,
            'measured_at' => $validated['measured_at'] ?? now(),
            ...$validated,
        ]);

        return response()->json(['data' => $this->shape($biometric)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['type' => ['sometimes', Rule::in(Biometric::TYPES)]]);

        $biometrics = Biometric::where('person_id', $request->user()->id)
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('measured_at')
            ->get()
            ->map(fn (Biometric $b) => $this->shape($b));

        return response()->json(['data' => $biometrics]);
    }

    /** @return array<string, mixed> */
    private function shape(Biometric $b): array
    {
        return [
            'id' => $b->id,
            'type' => $b->type,
            'value' => $b->value,
            'unit' => $b->unit,
            'measured_at' => $b->measured_at?->toIso8601String(),
        ];
    }
}
