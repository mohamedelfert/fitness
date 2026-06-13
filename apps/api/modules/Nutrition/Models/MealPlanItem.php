<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A prescribed meal within a MealPlanDay (DATABASE_DESIGN.md §2.2). */
class MealPlanItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'meal_plan_day_id', 'meal_type', 'food_item_id', 'recipe_id',
        'servings', 'target_kcal', 'target_macros_json', 'ordering', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'float',
            'target_kcal' => 'float',
            'target_macros_json' => 'array',
        ];
    }

    public function mealPlanDay(): BelongsTo
    {
        return $this->belongsTo(MealPlanDay::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
