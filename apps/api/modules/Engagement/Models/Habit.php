<?php

namespace Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\Models\Person;

/** A Person-owned recurring habit (FR-ENG-002). Plane A. Completions live in habit_logs. */
class Habit extends Model
{
    use HasFactory;
    use HasUlids;

    /** How often the habit recurs (extend in GLOSSARY.md before adding values). */
    public const CADENCES = ['daily', 'weekly'];

    protected $fillable = ['person_id', 'name', 'cadence', 'target_per_period', 'active'];

    protected function casts(): array
    {
        return [
            'target_per_period' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }
}
