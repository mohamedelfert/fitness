<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * A Person's AI weekly report for one ISO week (FR-AN-005). One row per person per week
 * (unique person_id+iso_week); append-only — never edited after the week is materialised.
 */
class WeeklyReport extends Model
{
    use HasUlids;

    /** Append-only: only created_at is tracked. */
    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'iso_week', 'week_start', 'summary', 'model'];

    protected function casts(): array
    {
        return ['week_start' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
