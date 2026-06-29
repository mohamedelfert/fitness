<?php

namespace Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single habit-completion event. APPEND-ONLY & IMMUTABLE (INV-002). */
class HabitLog extends Model
{
    use HasUlids;

    protected $fillable = ['habit_id', 'person_id', 'client_ulid', 'logged_at'];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
