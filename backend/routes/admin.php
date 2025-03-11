<?php

use App\Http\Controllers\API\Admin\{
    DashboardController,
    InviteController,
    MediaController,
    AuditController,
    AnalyticsController,
    RoleController
};

use App\Http\Controllers\API\Admin\{
    LearningPathController,
    UnitController,
    LessonController,
    SectionController,
    ExerciseController,
    QuizController,
    QuizQuestionController,
    VocabularyController,
    GuideBookEntryController,
    ContentController,
    ProgressController
};

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard & Analytics
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('analytics', [AnalyticsController::class, 'index']);

    // Content Management - Full CRUD operations for admins
    Route::apiResource('learning-paths', LearningPathController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('sections', SectionController::class);
    Route::apiResource('exercises', ExerciseController::class);
    Route::apiResource('quizzes', QuizController::class);
    Route::apiResource('quiz-questions', QuizQuestionController::class);
    Route::apiResource('vocabulary', VocabularyController::class);
    Route::apiResource('guide-entries', GuideBookEntryController::class);

    // Content Version Management
    Route::prefix('content')->group(function () {
        Route::post('{type}/{id}/publish', [ContentController::class, 'publish']);
        Route::post('{type}/{id}/unpublish', [ContentController::class, 'unpublish']);
        Route::get('{type}/{id}/versions', [ContentController::class, 'versions']);
        Route::post('{type}/{id}/restore/{version}', [ContentController::class, 'restore']);
    });

    // Progress Management
    Route::prefix('progress')->group(function () {
        Route::get('overview', [ProgressController::class, 'overview']);
        Route::get('users/{user}', [ProgressController::class, 'userProgress']);
        Route::get('content/{type}/{id}', [ProgressController::class, 'contentProgress']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('upload', [MediaController::class, 'upload']);
        Route::delete('{media}', [MediaController::class, 'destroy']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::post('invite', [InviteController::class, 'send']);
        Route::delete('invite/{invite}', [InviteController::class, 'cancel']);
    });

    // Roles & Permissions
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);

    // Audit Logs
    Route::get('audit-logs', [AuditController::class, 'index']);
    Route::get('audit-logs/{log}', [AuditController::class, 'show']);
});
