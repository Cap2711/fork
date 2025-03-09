<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ExerciseController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Unit Management
    Route::prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index']);
        Route::post('/', [UnitController::class, 'store']);
        Route::get('/{unit}', [UnitController::class, 'stats']);
        Route::put('/{unit}', [UnitController::class, 'update']);
        Route::delete('/{unit}', [UnitController::class, 'destroy']);
        Route::put('/reorder', [UnitController::class, 'updateOrder']);
        Route::post('/{unit}/toggle-lock', [UnitController::class, 'toggleLock']);
        
        // Lesson Management within Units
        Route::get('/{unit}/lessons', [LessonController::class, 'index']);
        Route::post('/{unit}/lessons', [LessonController::class, 'store']);
        Route::put('/{unit}/lessons/reorder', [LessonController::class, 'updateOrder']);
    });

    // Lesson Management
    Route::prefix('lessons')->group(function () {
        Route::get('/{lesson}', [LessonController::class, 'stats']);
        Route::put('/{lesson}', [LessonController::class, 'update']);
        Route::delete('/{lesson}', [LessonController::class, 'destroy']);
        Route::post('/{lesson}/clone', [LessonController::class, 'clone']);
    });

    // Exercise Management (to be implemented)
    Route::prefix('exercises')->group(function () {
        Route::get('/types', [ExerciseController::class, 'listTypes']);
        Route::post('/types', [ExerciseController::class, 'createType']);
        Route::put('/types/{type}', [ExerciseController::class, 'updateType']);
        Route::get('/templates', [ExerciseController::class, 'listTemplates']);
        Route::post('/{lesson}', [ExerciseController::class, 'create']);
        Route::put('/{exercise}', [ExerciseController::class, 'update']);
        Route::delete('/{exercise}', [ExerciseController::class, 'destroy']);
        Route::post('/{exercise}/clone', [ExerciseController::class, 'clone']);
    });

    // Progress & Analytics (placeholders)
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', 'AnalyticsController@overview');
        Route::get('/user-progress', 'AnalyticsController@userProgress');
        Route::get('/content-performance', 'AnalyticsController@contentPerformance');
        Route::get('/engagement', 'AnalyticsController@engagement');
    });

    // Content Import/Export
    Route::prefix('content')->group(function () {
        Route::post('/import', 'ContentController@import');
        Route::get('/export', 'ContentController@export');
        Route::post('/validate', 'ContentController@validate');
    });
});