<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Collection;
use Modules\Biometrics\Models\Biometric;
use Modules\Engagement\Models\Goal;
use Modules\Identity\Models\Person;

/**
 * Progress analysis + goal projection (FR-AN-001). Per-metric trend over a window and, per active
 * goal, a linear projection to its target. Goal→series is driven by the goal's UNIT (kg/lb→weight,
 * %→body_fat); reps/time/distance goals have no biometric series and read `no_matching_metric`
 * (those are performance trends, FR-AN-003, a separate item). Computed on read — these are cheap
 * person-scoped point queries (mirrors the nutrition summary), NOT the population-scale scoring of
 * DATABASE_DESIGN §3.5. ponytail: materialise + queue-refresh only if a person's history grows
 * large enough that the read measurably hurts.
 */
class ProgressAnalyzer
{
    private const WINDOW_DAYS = 90;

    /** Goal target unit → the biometric metric that tracks it. */
    private const UNIT_METRIC = ['kg' => 'weight', 'lb' => 'weight', '%' => 'body_fat'];

    /** @return array<string, mixed> */
    public function for(Person $person): array
    {
        $series = Biometric::where('person_id', $person->id)
            ->where('measured_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderBy('measured_at')
            ->get()
            ->groupBy('type');

        $goals = Goal::where('person_id', $person->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Goal $goal) => $this->projectGoal($goal, $series));

        return [
            'window_days' => self::WINDOW_DAYS,
            'metrics' => $series->map(fn ($rows, $type) => $this->summarize($type, $rows))->values()->all(),
            'goals' => $goals->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function summarize(string $type, Collection $rows): array
    {
        $slope = $this->slopePerDay($rows);

        return [
            'type' => $type,
            'unit' => $rows->last()->unit,
            'first' => round((float) $rows->first()->value, 2),
            'latest' => round((float) $rows->last()->value, 2),
            'change' => round((float) $rows->last()->value - (float) $rows->first()->value, 2),
            'trend_per_week' => $slope === null ? null : round($slope * 7, 3),
            'samples' => $rows->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function projectGoal(Goal $goal, Collection $series): array
    {
        $base = [
            'goal_id' => $goal->id,
            'type' => $goal->type,
            'target_value' => $goal->target_value === null ? null : (float) $goal->target_value,
            'target_unit' => $goal->target_unit,
            'target_date' => $goal->target_date?->toDateString(),
        ];

        $metric = self::UNIT_METRIC[$goal->target_unit] ?? null;
        if ($metric === null) {
            return [...$base, 'projection' => $this->projection('no_matching_metric')];
        }

        $rows = $series->get($metric);
        $slope = $rows === null ? null : $this->slopePerDay($rows);
        if ($slope === null || $goal->target_value === null) {
            return [...$base, 'projection' => $this->projection('insufficient_data', $metric)];
        }

        $current = (float) $rows->last()->value;
        $trendPerWeek = round($slope * 7, 3);
        $daysToTarget = $slope == 0.0 ? -1 : ((float) $goal->target_value - $current) / $slope;

        // ponytail: direction is inferred from the slope-vs-target sign — a goal already surpassed
        // (still 'active') reads as unfavorable until its status flips. Acceptable for a nudge.
        if ($daysToTarget <= 0) {
            return [...$base, 'projection' => $this->projection('trend_unfavorable', $metric, $current, $trendPerWeek, null, false)];
        }

        $projectedDate = now()->addDays((int) ceil($daysToTarget));
        $onTrack = $goal->target_date === null ? null : $projectedDate->lessThanOrEqualTo($goal->target_date->endOfDay());

        return [...$base, 'projection' => $this->projection('projected', $metric, $current, $trendPerWeek, $projectedDate->toDateString(), $onTrack)];
    }

    /** @return array<string, mixed> */
    private function projection(string $status, ?string $metric = null, ?float $current = null, ?float $trendPerWeek = null, ?string $projectedDate = null, ?bool $onTrack = null): array
    {
        return [
            'status' => $status,
            'metric' => $metric,
            'current' => $current,
            'trend_per_week' => $trendPerWeek,
            'projected_date' => $projectedDate,
            'on_track' => $onTrack,
        ];
    }

    /**
     * Least-squares slope in value-units per day. Null when <2 samples or all on the same instant
     * (zero time span → no trend, and avoids a divide-by-zero).
     */
    private function slopePerDay(Collection $rows): ?float
    {
        if ($rows->count() < 2) {
            return null;
        }

        $t0 = $rows->first()->measured_at->getTimestamp();
        $xs = $rows->map(fn ($r) => ($r->measured_at->getTimestamp() - $t0) / 86400)->all();
        $ys = $rows->map(fn ($r) => (float) $r->value)->all();
        $meanX = array_sum($xs) / count($xs);
        $meanY = array_sum($ys) / count($ys);

        $num = 0.0;
        $den = 0.0;
        foreach ($xs as $i => $x) {
            $num += ($x - $meanX) * ($ys[$i] - $meanY);
            $den += ($x - $meanX) ** 2;
        }

        return $den == 0.0 ? null : $num / $den;
    }
}
