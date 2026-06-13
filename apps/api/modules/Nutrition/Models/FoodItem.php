<?php

namespace Modules\Nutrition\Models;

use App\Casts\LocalizedJson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A food database entry (GLOSSARY.md, DATABASE_DESIGN.md §2.3). Plane A, shared asset.
 * Macro fields are per one serving; logging scales by servings.
 */
class FoodItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'source', 'external_ref', 'name_i18n', 'brand', 'barcode',
        'serving_units', 'kcal', 'protein', 'carbs', 'fat', 'micros', 'region',
    ];

    protected function casts(): array
    {
        return [
            'name_i18n' => LocalizedJson::class,
            'serving_units' => 'array',
            'micros' => 'array',
            'kcal' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
        ];
    }

    /**
     * Substring match across all localized names. DB-backed for now (Arabic substrings work
     * on utf8mb4 for free); swap this one scope for Meilisearch later.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // Case-insensitive substring over the localized names. CAST AS CHAR + LOWER is
        // portable across MariaDB (JSON=longtext, binary collation) and MySQL 8 (JSON type).
        return $query->whereRaw('LOWER(CAST(name_i18n AS CHAR)) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }

    public function localizedName(string $locale): ?string
    {
        $map = $this->name_i18n ?? [];

        return $map[$locale] ?? $map['en'] ?? (reset($map) ?: null);
    }
}
