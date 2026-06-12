<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A performed training Session (GLOSSARY.md). Table: sessions. */
class Session extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'person_id', 'workout_id', 'started_at', 'ended_at', 'perceived_effort', 'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }
}
