<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * A Person's AI daily recommendation for one date (FR-AI-004). One row per person per day
 * (unique person_id+rec_date); append-only — never edited after the day is materialised.
 */
class DailyRecommendation extends Model
{
    use HasUlids;

    /** Append-only: only created_at is tracked. */
    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'rec_date', 'message', 'model'];

    protected function casts(): array
    {
        return ['rec_date' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
