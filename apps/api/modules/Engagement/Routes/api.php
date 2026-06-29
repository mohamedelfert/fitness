<?php

use Illuminate\Support\Facades\Route;
use Modules\Engagement\Http\GamificationController;
use Modules\Engagement\Http\GoalController;
use Modules\Engagement\Http\HabitController;
use Modules\Engagement\Http\HabitLogController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('goals', [GoalController::class, 'index']);
    Route::post('goals', [GoalController::class, 'store']);
    Route::get('habits', [HabitController::class, 'index']);
    Route::post('habits', [HabitController::class, 'store']);
    Route::post('habit-logs', [HabitLogController::class, 'store']);
    Route::get('me/gamification', [GamificationController::class, 'show']);
});
