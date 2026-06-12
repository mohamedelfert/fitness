<?php

namespace Modules\Training\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name', 'slug', 'primary_muscles', 'equipment', 'instructions', 'contraindications',
    ];

    protected function casts(): array
    {
        return [
            'primary_muscles' => 'array',
            'equipment' => 'array',
            'instructions' => 'array',
            'contraindications' => 'array',
        ];
    }
}
