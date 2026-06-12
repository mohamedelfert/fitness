<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\HealthScreenController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('health-screen/questions', [HealthScreenController::class, 'questions']);
    Route::get('me/health-screen', [HealthScreenController::class, 'show']);
    Route::post('me/health-screen', [HealthScreenController::class, 'store']);
});
