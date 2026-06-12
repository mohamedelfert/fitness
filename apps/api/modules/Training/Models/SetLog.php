<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single logged set. APPEND-ONLY & IMMUTABLE (INV-002): no update/delete path is exposed;
 * corrections are recorded as new rows.
 */
class SetLog extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'session_id', 'person_id', 'exercise_id', 'set_index',
        'reps', 'load', 'rpe', 'rir', 'client_ulid', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'load' => 'float',
            'rpe' => 'float',
            'rir' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
