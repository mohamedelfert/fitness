<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Models\WeeklyReport;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Services\WeeklyReportGenerator;

/**
 * AI weekly report (FR-AN-005) — `GET /v1/me/reports/weekly`. Same preconditions as the other AI
 * features (the `ai-plan.generate` gate → 403, completed onboarding → 422). Materialised once per
 * ISO week: a cache hit returns the stored summary for free; a miss runs the generator, charged
 * one AICredit (402 if the wallet can't cover it) and debited only on success. Advisory-only —
 * the contraindication sandwich does not apply.
 */
class WeeklyReportController extends Controller
{
    public function show(Request $request, WeeklyReportGenerator $generator, AiCreditMeter $meter): JsonResponse
    {
        $person = $request->user();

        Gate::forUser($person)->authorize('ai-plan.generate');

        if (! $person->isOnboardingComplete()) {
            throw ValidationException::withMessages(['onboarding' => 'Complete onboarding before requesting a weekly report.']);
        }

        $isoWeek = now()->format('o-\WW');

        $existing = WeeklyReport::where('person_id', $person->id)->where('iso_week', $isoWeek)->first();
        if ($existing !== null) {
            return $this->present($existing);
        }

        $cost = $meter->costFor('weekly_report');
        $meter->ensureCanAfford($person, $cost);

        $report = $generator->generate($person, $isoWeek, now()->startOfWeek()->toDateString());

        $meter->debit($person, $cost, 'weekly_report', $report);

        return $this->present($report);
    }

    private function present(WeeklyReport $report): JsonResponse
    {
        return response()->json(['data' => [
            'iso_week' => $report->iso_week,
            'week_start' => $report->week_start->toDateString(),
            'summary' => $report->summary,
        ]]);
    }
}
