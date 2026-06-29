<?php

use Illuminate\Support\Facades\Route;
use Modules\AiOrchestration\Http\AiCreditController;
use Modules\AiOrchestration\Http\CoachChatController;
use Modules\AiOrchestration\Http\DailyRecommendationController;
use Modules\AiOrchestration\Http\ExerciseAlternativeController;
use Modules\AiOrchestration\Http\HabitNudgeController;
use Modules\AiOrchestration\Http\MealPlanGenerationController;
use Modules\AiOrchestration\Http\PlanAdjustmentController;
use Modules\AiOrchestration\Http\ProgramGenerationController;
use Modules\AiOrchestration\Http\RecoveryController;
use Modules\AiOrchestration\Http\WeeklyReportController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('ai/program', [ProgramGenerationController::class, 'store']);
    Route::post('ai/meal-plan', [MealPlanGenerationController::class, 'store']);
    Route::post('ai/exercise-alternatives', [ExerciseAlternativeController::class, 'store']);
    Route::post('ai/plan-adjustment', [PlanAdjustmentController::class, 'store']);
    Route::get('ai/recommendations/today', [DailyRecommendationController::class, 'today']);
    Route::get('ai/recovery', [RecoveryController::class, 'show']);
    Route::get('ai/habit-nudge', [HabitNudgeController::class, 'show']);
    Route::get('me/reports/weekly', [WeeklyReportController::class, 'show']);
    Route::post('ai/coach/chat', [CoachChatController::class, 'store']);
    Route::get('ai/coach/chat', [CoachChatController::class, 'history']);
    Route::get('me/ai-credits', [AiCreditController::class, 'show']);
});
