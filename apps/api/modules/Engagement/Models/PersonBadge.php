<?php

namespace Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/** A badge a Person has earned (FR-ENG-003). APPEND-ONLY — once earned, never revoked or edited. */
class PersonBadge extends Model
{
    use HasUlids;

    /** Append-only: only earned_at is tracked. */
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = ['person_id', 'badge_slug', 'earned_at'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
