<?php

namespace Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * A Person's goal (FR-ENG-001, GLOSSARY.md). Plane A — owned by the Person.
 */
class Goal extends Model
{
    use HasFactory;
    use HasUlids;

    /** Goal vocabulary (extend in GLOSSARY.md before adding values). */
    public const TYPES = [
        'fat_loss', 'muscle_gain', 'strength', 'endurance',
        'body_recomposition', 'general_health',
    ];

    protected $fillable = [
        'person_id', 'type', 'target_value', 'target_unit', 'target_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'target_date' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
