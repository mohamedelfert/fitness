<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * One turn in a Person's AI coach conversation (FR-AI-008) — role `user` or `assistant`.
 * Append-only; never edited once written.
 */
class CoachMessage extends Model
{
    use HasUlids;

    /** Append-only: only created_at is tracked. */
    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'role', 'content'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
