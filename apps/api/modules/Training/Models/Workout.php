<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An ordered session template within a Program (DATABASE_DESIGN.md §2.2). */
class Workout extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['program_id', 'day_index', 'name', 'ordering'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('order');
    }
}
