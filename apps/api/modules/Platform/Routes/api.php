<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\HealthController;

// Registered under /v1 by ModuleServiceProvider. Public (no auth) for probes.
Route::get('health', HealthController::class)->name('platform.health');
