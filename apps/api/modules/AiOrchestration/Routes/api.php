<?php

use Illuminate\Support\Facades\Route;
use Modules\AiOrchestration\Http\AiCreditController;
use Modules\AiOrchestration\Http\ExerciseAlternativeController;
use Modules\AiOrchestration\Http\MealPlanGenerationController;
use Modules\AiOrchestration\Http\ProgramGenerationController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('ai/program', [ProgramGenerationController::class, 'store']);
    Route::post('ai/meal-plan', [MealPlanGenerationController::class, 'store']);
    Route::post('ai/exercise-alternatives', [ExerciseAlternativeController::class, 'store']);
    Route::get('me/ai-credits', [AiCreditController::class, 'show']);
});
