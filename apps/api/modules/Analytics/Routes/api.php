<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\ProgressController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/progress', [ProgressController::class, 'show']);
});
