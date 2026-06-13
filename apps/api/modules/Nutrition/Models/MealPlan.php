<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\Models\Person;

/**
 * A structured nutrition plan a Person follows (GLOSSARY.md, DATABASE_DESIGN.md §2.2) —
 * the nutrition analog of a Program. Plane A, person-owned.
 */
class MealPlan extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'person_id', 'source', 'coach_id', 'template_id', 'name', 'daily_targets_json', 'start_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'daily_targets_json' => 'array',
            'start_date' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(MealPlanDay::class)->orderBy('ordering');
    }
}
