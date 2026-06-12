<?php

namespace Modules\Identity\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\HealthScreen;
use Modules\Identity\Support\Parq;

class HealthScreenController extends Controller
{
    /** GET /v1/health-screen/questions — the PAR-Q+ questionnaire to render. */
    public function questions(): JsonResponse
    {
        return response()->json(['data' => Parq::questions()]);
    }

    /** GET /v1/me/health-screen — current status + latest screening. */
    public function show(Request $request): JsonResponse
    {
        $person = $request->user();
        $latest = HealthScreen::where('person_id', $person->id)
            ->latest('screened_at')->first();

        return response()->json(['data' => [
            'status' => $person->health_screen_status,
            'latest' => $latest ? $this->shape($latest) : null,
        ]]);
    }

    /** POST /v1/me/health-screen — submit answers, score, gate (FR-AI-007). */
    public function store(Request $request): JsonResponse
    {
        $rules = ['answers' => ['required', 'array']];
        foreach (Parq::keys() as $key) {
            $rules["answers.$key"] = ['required', 'boolean'];
        }
        $validated = $request->validate($rules);

        $scored = Parq::score($validated['answers']);

        $screen = HealthScreen::create([
            'person_id' => $request->user()->id,
            'answers' => $validated['answers'],
            'result' => $scored['result'],
            'flagged_questions' => $scored['flagged_questions'],
            'screened_at' => now(),
        ]);

        // The latest screening drives the Person's gate status.
        $request->user()->forceFill(['health_screen_status' => $scored['result']])->save();

        return response()->json(['data' => $this->shape($screen)], 201);
    }

    private function shape(HealthScreen $s): array
    {
        return [
            'id' => $s->id,
            'result' => $s->result,
            'status' => $s->result,
            'flagged_questions' => $s->flagged_questions ?? [],
            'screened_at' => $s->screened_at?->toIso8601String(),
        ];
    }
}
