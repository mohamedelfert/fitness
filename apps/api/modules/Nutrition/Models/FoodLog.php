<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logged consumption event. APPEND-ONLY & IMMUTABLE (INV-002): no update/delete path;
 * corrections are new rows. Macros are a snapshot taken at log time.
 */
class FoodLog extends Model
{
    use HasFactory;
    use HasUlids;

    public const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

    public const SOURCES = ['search', 'barcode', 'image', 'voice', 'custom'];

    protected $fillable = [
        'person_id', 'food_item_id', 'recipe_id', 'meal_type', 'servings',
        'kcal', 'protein', 'carbs', 'fat', 'micros_json', 'source', 'client_ulid', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'float',
            'kcal' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
            'micros_json' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
