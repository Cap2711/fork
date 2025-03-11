<?php

use App\Http\Controllers\API\LearningPathController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\SectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('learning-paths', [LearningPathController::class, 'index']);
Route::get('learning-paths/{learningPath}', [LearningPathController::class, 'show']);
Route::get('learning-paths/level/{level}', [LearningPathController::class, 'byLevel']);

// Units public routes
Route::get('learning-paths/{learningPath}/units', [UnitController::class, 'index']);
Route::get('units/{unit}', [UnitController::class, 'show']);

// Lessons public routes
Route::get('units/{unit}/lessons', [LessonController::class, 'index']);
Route::get('lessons/{lesson}', [LessonController::class, 'show']);

// Sections public routes
Route::get('lessons/{lesson}/sections', [SectionController::class, 'index']);
Route::get('sections/{section}', [SectionController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Learning paths management
    Route::middleware('can:manage-learning-paths')->group(function () {
        Route::post('learning-paths', [LearningPathController::class, 'store']);
        Route::put('learning-paths/{learningPath}', [LearningPathController::class, 'update']);
        Route::delete('learning-paths/{learningPath}', [LearningPathController::class, 'destroy']);
        Route::patch('learning-paths/{learningPath}/status', [LearningPathController::class, 'updateStatus']);

        // Units management
        Route::post('units', [UnitController::class, 'store']);
        Route::put('units/{unit}', [UnitController::class, 'update']);
        Route::delete('units/{unit}', [UnitController::class, 'destroy']);
        Route::post('learning-paths/{learningPath}/units/reorder', [UnitController::class, 'reorder']);

        // Lessons management
        Route::post('lessons', [LessonController::class, 'store']);
        Route::put('lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::post('units/{unit}/lessons/reorder', [LessonController::class, 'reorder']);

        // Sections management
        Route::post('sections', [SectionController::class, 'store']);
        Route::put('sections/{section}', [SectionController::class, 'update']);
        Route::delete('sections/{section}', [SectionController::class, 'destroy']);
        Route::post('lessons/{lesson}/sections/reorder', [SectionController::class, 'reorder']);
    });

    // User progress
    Route::get('learning-paths/{learningPath}/progress', [LearningPathController::class, 'progress']);
    Route::get('units/{unit}/progress', [UnitController::class, 'progress']);
    Route::get('lessons/{lesson}/progress', [LessonController::class, 'progress']);
    Route::get('sections/{section}/progress', [SectionController::class, 'progress']);
});

// Health check
Route::get('health', function () {
    return response()->json(['status' => 'healthy']);
});