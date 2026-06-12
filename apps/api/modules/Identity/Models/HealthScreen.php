<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A PAR-Q+ screening record (append-only). */
class HealthScreen extends Model
{
    use HasUlids;

    protected $fillable = [
        'person_id', 'answers', 'result', 'flagged_questions', 'screened_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'flagged_questions' => 'array',
            'screened_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
