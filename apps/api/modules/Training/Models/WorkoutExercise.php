<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A prescription row within a Workout (DATABASE_DESIGN.md §2.2). */
class WorkoutExercise extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'workout_id', 'exercise_id', 'order',
        'target_sets', 'target_reps', 'target_load', 'rest_sec', 'tempo', 'notes',
    ];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
