<?php

namespace Modules\Nutrition\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Water intake event. APPEND-ONLY & IMMUTABLE (INV-002). */
class WaterLog extends Model
{
    use HasUlids;

    protected $fillable = ['person_id', 'amount_ml', 'client_ulid', 'logged_at'];

    protected function casts(): array
    {
        return [
            'amount_ml' => 'integer',
            'logged_at' => 'datetime',
        ];
    }
}
