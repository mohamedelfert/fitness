<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The portable, user-owned identity (GLOSSARY.md). Plane A (central) — not tenant-scoped.
 * A single Person may simultaneously be a B2C user, a coach's Client, and a gym Member.
 */
class Person extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;       // ULID primary key (ADR-009)
    use Notifiable;

    protected $table = 'persons';

    protected $fillable = [
        'display_name', 'email', 'phone', 'password',
        'dob', 'sex', 'height_cm',
        'locale', 'unit_system', 'timezone', 'country',
        'health_screen_status', 'onboarding_state',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_state' => 'array',
            'dob' => 'date',
        ];
    }
}
