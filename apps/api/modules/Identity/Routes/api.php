<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\HealthScreenController;
use Modules\Identity\Http\MeController;
use Modules\Identity\Http\OnboardingController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::post('onboarding', [OnboardingController::class, 'store']);

    Route::get('health-screen/questions', [HealthScreenController::class, 'questions']);
    Route::get('me/health-screen', [HealthScreenController::class, 'show']);
    Route::post('me/health-screen', [HealthScreenController::class, 'store']);
});
