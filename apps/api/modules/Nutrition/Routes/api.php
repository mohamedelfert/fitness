<?php

use Illuminate\Support\Facades\Route;
use Modules\Nutrition\Http\FoodController;
use Modules\Nutrition\Http\FoodLogController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('foods', [FoodController::class, 'index']);
    Route::get('foods/barcode/{code}', [FoodController::class, 'barcode']);

    Route::post('food-logs', [FoodLogController::class, 'store']);
    Route::get('me/nutrition/summary', [FoodLogController::class, 'summary']);
});
