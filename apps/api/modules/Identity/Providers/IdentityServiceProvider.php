<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Models\Person;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Safety gate (FR-AI-007, INV-005): only a PAR-Q+-cleared Person may
        // receive AI-generated plans. AI plan/meal endpoints will Gate::authorize this.
        Gate::define('ai-plan.generate', fn (Person $person) => $person->health_screen_status === 'passed');
    }
}
