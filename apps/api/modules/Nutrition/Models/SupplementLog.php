<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Supplement intake event. APPEND-ONLY & IMMUTABLE (INV-002). */
class SupplementLog extends Model
{
    use HasUlids;

    protected $fillable = ['person_id', 'name', 'dose', 'unit', 'client_ulid', 'logged_at'];

    protected function casts(): array
    {
        return [
            'dose' => 'float',
            'logged_at' => 'datetime',
        ];
    }
}
