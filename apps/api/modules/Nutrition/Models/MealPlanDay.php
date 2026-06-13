<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An ordered day within a MealPlan (DATABASE_DESIGN.md §2.2). */
class MealPlanDay extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['meal_plan_id', 'day_index', 'name', 'ordering'];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class)->orderBy('ordering');
    }
}
