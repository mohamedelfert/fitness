<?php

namespace Modules\Platform\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A platform operator (Super-Admin surface). Authenticates against the `admin` guard,
 * separate from end-user Persons.
 */
class PlatformUser extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasUlids;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Phase 0: any platform user may access the super-admin panel.
        // Later: gate by `role`/permissions per ROLES_PERMISSIONS.md §3.4.
        return true;
    }
}
