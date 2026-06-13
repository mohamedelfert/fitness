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
            'height_cm' => 'integer',
        ];
    }

    /**
     * The onboarding training profile the AI Brain consumes (experience level, equipment,
     * schedule, diet, injuries). Lives under onboarding_state.profile (DATABASE_DESIGN §2.1).
     *
     * @return array<string, mixed>
     */
    public function trainingProfile(): array
    {
        return $this->onboarding_state['profile'] ?? [];
    }

    public function isOnboardingComplete(): bool
    {
        return (bool) ($this->onboarding_state['completed'] ?? false);
    }

    /** Merge captured training-profile fields, preserving any already set. */
    public function mergeTrainingProfile(array $fields): void
    {
        $state = $this->onboarding_state ?? [];
        $state['profile'] = array_merge($state['profile'] ?? [], $fields);
        $this->onboarding_state = $state;
    }

    public function markOnboardingComplete(): void
    {
        $state = $this->onboarding_state ?? [];
        $state['completed'] = true;
        $state['completed_at'] = now()->toIso8601String();
        $this->onboarding_state = $state;
    }
}
