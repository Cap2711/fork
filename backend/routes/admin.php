<?php

use App\Http\Controllers\API\Admin\{
    AdminDashboardController,
    AdminInviteController,
    AdminMediaController,
    AdminAuditController,
    AdminAnalyticsController,
    AdminRoleController
};

use App\Http\Controllers\API\Admin\{
    AdminLearningPathController,
    AdminUnitController,
    AdminLessonController,
    AdminSectionController,
    AdminExerciseController,
    AdminQuizController,
    AdminQuizQuestionController,
    AdminVocabularyController,
    AdminGuideBookEntryController,
    AdminContentController,
    AdminProgressController
};

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard & Analytics
    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('analytics', [AdminAnalyticsController::class, 'index']);

    // Content Management - Full CRUD operations for admins
    Route::apiResource('learning-paths', AdminLearningPathController::class);
    Route::apiResource('units', AdminUnitController::class);
    Route::apiResource('lessons', AdminLessonController::class);
    Route::apiResource('sections', AdminSectionController::class);
    Route::apiResource('exercises', AdminExerciseController::class);
    Route::apiResource('quizzes', AdminQuizController::class);
    Route::apiResource('quiz-questions', AdminQuizQuestionController::class);
    Route::apiResource('vocabulary', AdminVocabularyController::class);
    Route::apiResource('guide-entries', AdminGuideBookEntryController::class);

    // Review Workflow for Learning Paths
    Route::prefix('learning-paths')->group(function () {
        Route::post('{learning_path}/submit-for-review', [AdminLearningPathController::class, 'submitForReview']);
        Route::post('{learning_path}/approve-review', [AdminLearningPathController::class, 'approveReview']);
        Route::post('{learning_path}/reject-review', [AdminLearningPathController::class, 'rejectReview']);
        Route::patch('{learning_path}/status', [AdminLearningPathController::class, 'updateStatus']);
        Route::post('{learning_path}/reorder-units', [AdminLearningPathController::class, 'reorderUnits']);
    });

    // Review Workflow for Units
    Route::prefix('units')->group(function () {
        Route::post('{unit}/submit-for-review', [AdminUnitController::class, 'submitForReview']);
        Route::post('{unit}/approve-review', [AdminUnitController::class, 'approveReview']);
        Route::post('{unit}/reject-review', [AdminUnitController::class, 'rejectReview']);
        Route::patch('{unit}/status', [AdminUnitController::class, 'updateStatus']);
        Route::post('{unit}/reorder-lessons', [AdminUnitController::class, 'reorderLessons']);
    });

    // Review Workflow for Lessons
    Route::prefix('lessons')->group(function () {
        Route::post('{lesson}/submit-for-review', [AdminLessonController::class, 'submitForReview']);
        Route::post('{lesson}/approve-review', [AdminLessonController::class, 'approveReview']);
        Route::post('{lesson}/reject-review', [AdminLessonController::class, 'rejectReview']);
        Route::patch('{lesson}/status', [AdminLessonController::class, 'updateStatus']);
        Route::post('{lesson}/reorder-sections', [AdminLessonController::class, 'reorderSections']);
    });

    // Review Workflow for Sections
    Route::prefix('sections')->group(function () {
        Route::post('{section}/submit-for-review', [AdminSectionController::class, 'submitForReview']);
        Route::post('{section}/approve-review', [AdminSectionController::class, 'approveReview']);
        Route::post('{section}/reject-review', [AdminSectionController::class, 'rejectReview']);
        Route::patch('{section}/status', [AdminSectionController::class, 'updateStatus']);
        Route::post('{section}/reorder-exercises', [AdminSectionController::class, 'reorderExercises']);
    });

    // Content Version Management
    Route::prefix('content')->group(function () {
        Route::post('{type}/{id}/publish', [AdminContentController::class, 'publish']);
        Route::post('{type}/{id}/unpublish', [AdminContentController::class, 'unpublish']);
        Route::get('{type}/{id}/versions', [AdminContentController::class, 'versions']);
        Route::post('{type}/{id}/restore/{version}', [AdminContentController::class, 'restore']);
    });

    // Progress Management
    Route::prefix('progress')->group(function () {
        Route::get('overview', [AdminProgressController::class, 'overview']);
        Route::get('users/{user}', [AdminProgressController::class, 'userProgress']);
        Route::get('content/{type}/{id}', [AdminProgressController::class, 'contentProgress']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('upload', [AdminMediaController::class, 'upload']);
        Route::delete('{media}', [AdminMediaController::class, 'destroy']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::post('invite', [AdminInviteController::class, 'send']);
        Route::delete('invite/{invite}', [AdminInviteController::class, 'cancel']);
    });

    // Roles & Permissions
    Route::apiResource('roles', AdminRoleController::class);
    Route::post('roles/{role}/permissions', [AdminRoleController::class, 'updatePermissions']);

    // Audit Logs
    Route::get('audit-logs', [AdminAuditController::class, 'index']);
    Route::get('audit-logs/{log}', [AdminAuditController::class, 'show']);
});
