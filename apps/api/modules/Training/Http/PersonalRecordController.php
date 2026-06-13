<?php

namespace Modules\Training\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Training\Models\PersonalRecord;

/** GET /v1/me/records — the Person's current PRs (read-model, FR-TRN-004). */
class PersonalRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = PersonalRecord::with('exercise')
            ->where('person_id', $request->user()->id)
            ->get()
            ->map(fn (PersonalRecord $r) => [
                'exercise_id' => $r->exercise_id,
                'exercise_name' => $r->exercise?->name,
                'metric' => $r->metric,
                'value' => $r->value,
                'achieved_at' => $r->achieved_at?->toIso8601String(),
                'session_id' => $r->session_id,
            ]);

        return response()->json(['data' => $records]);
    }
}
