<?php

use Illuminate\Support\Facades\Route;
use Modules\Nutrition\Http\FoodController;
use Modules\Nutrition\Http\FoodLogController;
use Modules\Nutrition\Http\IntakeLogController;
use Modules\Nutrition\Http\MealPlanController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('foods', [FoodController::class, 'index']);
    Route::get('foods/barcode/{code}', [FoodController::class, 'barcode']);

    Route::post('food-logs', [FoodLogController::class, 'store']);
    Route::post('water-logs', [IntakeLogController::class, 'water']);
    Route::post('supplement-logs', [IntakeLogController::class, 'supplement']);
    Route::get('me/nutrition/summary', [FoodLogController::class, 'summary']);

    Route::get('meal-plans', [MealPlanController::class, 'index']);
    Route::get('meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
});
