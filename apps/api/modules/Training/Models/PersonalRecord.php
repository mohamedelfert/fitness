<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Person's current best for an (exercise, metric) — a read-model derived from set_logs
 * (FR-TRN-004). Refreshed async by DetectPersonalRecords; never written on the hot path.
 */
class PersonalRecord extends Model
{
    use HasFactory;
    use HasUlids;

    /** Derived metrics (extend GLOSSARY.md before adding). */
    public const METRICS = ['max_load', 'est_1rm', 'max_reps'];

    protected $fillable = [
        'person_id', 'exercise_id', 'metric', 'value', 'achieved_at', 'session_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'achieved_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
