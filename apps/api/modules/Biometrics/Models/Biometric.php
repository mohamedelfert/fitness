<?php

namespace Modules\Biometrics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\Person;

/**
 * A single body measurement (FR-BIO-001). APPEND-ONLY & IMMUTABLE (INV-002) — corrections are
 * new rows, never edits. `type` is one of TYPES; circumference sites are their own type.
 */
class Biometric extends Model
{
    use HasUlids;

    /** Allowed measurement types — extend as the body-tracking surface grows. */
    public const TYPES = ['weight', 'body_fat', 'waist', 'hip', 'chest', 'arm', 'thigh', 'neck'];

    protected $fillable = ['person_id', 'type', 'value', 'unit', 'client_ulid', 'measured_at'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'measured_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
