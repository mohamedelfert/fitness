<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Exercise library entry (DATABASE_DESIGN.md §2.3). Plane A, shared read-mostly asset.
 * `contraindications` powers the AI safety gate (FR-AI-007).
 */
class Exercise extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name', 'slug', 'primary_muscles', 'secondary_muscles', 'equipment',
        'mechanics', 'instructions', 'media_keys', 'contraindications',
    ];

    protected function casts(): array
    {
        return [
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'equipment' => 'array',
            'instructions' => 'array',
            'media_keys' => 'array',
            'contraindications' => 'array',
        ];
    }

    /**
     * Substring match on the canonical name. DB-backed for now; swap this one method for
     * Meilisearch later. Plain `contains` only — no collation-specific ranking, since the
     * local MariaDB and prod MySQL 8 collate differently (SESSION_HANDOFF §3).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', '%'.$term.'%');
    }

    public function scopeForMuscle(Builder $query, string $muscle): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereJsonContains('primary_muscles', $muscle)
            ->orWhereJsonContains('secondary_muscles', $muscle));
    }

    public function scopeForEquipment(Builder $query, string $equipment): Builder
    {
        return $query->whereJsonContains('equipment', $equipment);
    }

    /** Resolve localized instructions for a locale, falling back to English then any value. */
    public function localizedInstructions(string $locale): ?string
    {
        $map = $this->instructions ?? [];

        return $map[$locale] ?? $map['en'] ?? (reset($map) ?: null);
    }
}
