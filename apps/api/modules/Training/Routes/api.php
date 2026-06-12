<?php

use Illuminate\Support\Facades\Route;
use Modules\Training\Http\HistoryController;
use Modules\Training\Http\SessionController;
use Modules\Training\Http\SetLogController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('sessions', [SessionController::class, 'store']);
    Route::post('sessions/{session}/sets', [SetLogController::class, 'store']);
    Route::get('me/history', [HistoryController::class, 'index']);
});
