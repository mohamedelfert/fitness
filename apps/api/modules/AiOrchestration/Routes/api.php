<?php

use Illuminate\Support\Facades\Route;
use Modules\AiOrchestration\Http\ProgramGenerationController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('ai/program', [ProgramGenerationController::class, 'store']);
});
