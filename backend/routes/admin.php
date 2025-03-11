<?php

use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\RoleController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\ContentController;
use App\Http\Controllers\API\Admin\MediaController;
use App\Http\Controllers\API\Admin\AuditController;
use App\Http\Controllers\API\Admin\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
    Route::get('dashboard/content-stats', [DashboardController::class, 'contentStats']);

    // User & Role Management
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/roles', [UserController::class, 'updateRoles']);

    // Content Management
    Route::prefix('content')->group(function () {
        // Content CRUD operations
        Route::get('draft', [ContentController::class, 'getDrafts']);
        Route::get('published', [ContentController::class, 'getPublished']);
        Route::get('archived', [ContentController::class, 'getArchived']);
        
        // Content Versioning
        Route::get('{type}/{id}/versions', [ContentController::class, 'getVersions']);
        Route::get('{type}/{id}/versions/{version}', [ContentController::class, 'getVersion']);
        Route::post('{type}/{id}/versions/{version}/restore', [ContentController::class, 'restoreVersion']);
        
        // Bulk Operations
        Route::post('bulk/publish', [ContentController::class, 'bulkPublish']);
        Route::post('bulk/archive', [ContentController::class, 'bulkArchive']);
        Route::post('bulk/delete', [ContentController::class, 'bulkDelete']);

        // Import/Export
        Route::post('import', [ContentController::class, 'import']);
        Route::post('export', [ContentController::class, 'export']);
        
        // Preview
        Route::get('{type}/{id}/preview', [ContentController::class, 'preview']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('upload', [MediaController::class, 'upload']);
        Route::post('bulk-upload', [MediaController::class, 'bulkUpload']);
        Route::delete('{media}', [MediaController::class, 'destroy']);
        Route::post('{media}/optimize', [MediaController::class, 'optimize']);
        Route::post('{media}/generate-conversions', [MediaController::class, 'generateConversions']);
    });

    // Audit Logs
    Route::prefix('audit')->group(function () {
        Route::get('logs', [AuditController::class, 'index']);
        Route::get('logs/{id}', [AuditController::class, 'show']);
        Route::get('logs/user/{userId}', [AuditController::class, 'userActivity']);
        Route::get('logs/content/{type}/{id}', [AuditController::class, 'contentHistory']);
        Route::get('logs/export', [AuditController::class, 'export']);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('user-engagement', [AnalyticsController::class, 'userEngagement']);
        Route::get('content-performance', [AnalyticsController::class, 'contentPerformance']);
        Route::get('learning-progress', [AnalyticsController::class, 'learningProgress']);
        Route::get('quiz-statistics', [AnalyticsController::class, 'quizStatistics']);
        Route::get('user-retention', [AnalyticsController::class, 'userRetention']);
        Route::post('generate-report', [AnalyticsController::class, 'generateReport']);
    });
});