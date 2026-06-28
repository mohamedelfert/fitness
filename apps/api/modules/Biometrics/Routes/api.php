<?php

use Illuminate\Support\Facades\Route;
use Modules\Biometrics\Http\BiometricController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('biometrics', [BiometricController::class, 'store']);
    Route::get('biometrics', [BiometricController::class, 'index']);
});
