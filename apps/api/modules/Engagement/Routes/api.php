<?php

use Illuminate\Support\Facades\Route;
use Modules\Engagement\Http\GoalController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('goals', [GoalController::class, 'index']);
    Route::post('goals', [GoalController::class, 'store']);
});
