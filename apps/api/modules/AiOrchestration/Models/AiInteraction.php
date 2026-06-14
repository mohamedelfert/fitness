<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * Audit + cost record of a single AI Brain call (DATABASE_DESIGN.md §2.5). Append-only
 * by intent: a call's outcome is history, never edited. `safety_verdict` records whether
 * the safety post-eval cleared the output (passed), blocked it (rejected), or the call
 * could not be evaluated (error: malformed / hallucinated output).
 */
class AiInteraction extends Model
{
    use HasUlids;

    /** Only created_at is tracked — interactions are append-only (no updated_at column). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'person_id', 'tenant_id', 'feature', 'model', 'tier',
        'tokens_in', 'tokens_out', 'cost_micros', 'latency_ms',
        'confidence', 'safety_verdict', 'accepted',
    ];

    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_micros' => 'integer',
            'latency_ms' => 'integer',
            'confidence' => 'integer',
            'accepted' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
