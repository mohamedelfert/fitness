<?php

use Illuminate\Support\Facades\Route;
use Modules\Wearables\Http\WearableController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('wearables/ingest', [WearableController::class, 'ingest']);
    Route::get('wearables', [WearableController::class, 'index']);
});
