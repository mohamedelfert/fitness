<?php

use Illuminate\Support\Facades\Route;
use Modules\Training\Http\ExerciseController;
use Modules\Training\Http\HistoryController;
use Modules\Training\Http\ProgramController;
use Modules\Training\Http\SessionController;
use Modules\Training\Http\SetLogController;

// Registered under /v1 with the `api` group by ModuleServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::get('exercises/{exercise}', [ExerciseController::class, 'show']);

    Route::get('programs', [ProgramController::class, 'index']);
    Route::get('programs/{program}', [ProgramController::class, 'show']);

    Route::post('sessions', [SessionController::class, 'store']);
    Route::post('sessions/{session}/sets', [SetLogController::class, 'store']);
    Route::get('me/history', [HistoryController::class, 'index']);
});
