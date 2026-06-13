<?php

namespace Modules\Training\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Training\Models\PersonalRecord;
use Modules\Training\Models\Session;
use Modules\Training\Models\SetLog;

/**
 * Refreshes the personal_records read-model for the exercises in a finished session
 * (FR-TRN-004). Async (queued) so it never sits on the logging hot path (NFR-SCAL-001).
 * Recomputes each metric from the Person's full set_log history → current best, idempotent.
 */
class DetectPersonalRecords implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $sessionId) {}

    public function handle(): void
    {
        $session = Session::find($this->sessionId);
        if ($session === null) {
            return;
        }

        $exerciseIds = SetLog::query()
            ->where('session_id', $session->id)
            ->distinct()
            ->pluck('exercise_id');

        foreach ($exerciseIds as $exerciseId) {
            $sets = SetLog::query()
                ->where('person_id', $session->person_id)
                ->where('exercise_id', $exerciseId)
                ->get();

            // Heaviest load lifted.
            $this->record($session->person_id, $exerciseId, 'max_load',
                $sets->filter(fn (SetLog $s) => $s->load !== null),
                fn (SetLog $s) => (float) $s->load);

            // Estimated 1RM (Epley): load × (1 + reps/30).
            $this->record($session->person_id, $exerciseId, 'est_1rm',
                $sets->filter(fn (SetLog $s) => $s->load !== null && $s->reps > 0),
                fn (SetLog $s) => (float) $s->load * (1 + $s->reps / 30));

            // Most reps in a single set (covers bodyweight progress).
            $this->record($session->person_id, $exerciseId, 'max_reps',
                $sets, fn (SetLog $s) => (float) $s->reps);
        }
    }

    /**
     * @param  Collection<int, SetLog>  $sets
     * @param  callable(SetLog): float  $value
     */
    private function record(string $personId, string $exerciseId, string $metric, Collection $sets, callable $value): void
    {
        $best = $sets->sortByDesc($value)->first();
        if ($best === null) {
            return;
        }

        PersonalRecord::updateOrCreate(
            ['person_id' => $personId, 'exercise_id' => $exerciseId, 'metric' => $metric],
            [
                'value' => round($value($best), 2),
                'achieved_at' => $best->logged_at,
                'session_id' => $best->session_id,
            ],
        );
    }
}
