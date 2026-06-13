<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\Models\Person;

/**
 * A structured training plan a Person follows (GLOSSARY.md, DATABASE_DESIGN.md §2.2).
 * Plane A, person-owned.
 */
class Program extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'person_id', 'source', 'coach_id', 'template_id', 'name', 'start_date', 'mesocycle_json', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'mesocycle_json' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class)->orderBy('ordering');
    }
}
