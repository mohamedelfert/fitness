<?php

namespace Modules\Wearables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * A single wearable reading (FR-BIO-003). APPEND-ONLY & IMMUTABLE (INV-002).
 */
class WearableStream extends Model
{
    use HasUlids;

    /** Append-only: only created_at is tracked. */
    public const UPDATED_AT = null;

    /** Supported metrics — extend as connectors surface more streams. */
    public const METRICS = ['hr', 'hrv', 'sleep', 'steps'];

    protected $fillable = ['person_id', 'source', 'metric', 'value', 'client_ulid', 'recorded_at'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
