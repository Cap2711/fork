<?php

use App\Http\Controllers\Auth\AdminInviteController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LearningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth routes
Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/register', [LoginController::class, 'register']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/user', [LoginController::class, 'user'])->middleware('auth:sanctum');

// Google OAuth routes
Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/invite', [AdminInviteController::class, 'sendInvite']);
    Route::get('/admin/invites', [AdminInviteController::class, 'listInvites']);
    Route::delete('/admin/invites/{invite}', [AdminInviteController::class, 'revokeInvite']);
});

// Admin invite validation (public)
Route::post('/admin/invite/validate', [AdminInviteController::class, 'validateInvite']);

// Learning routes
Route::middleware('auth:sanctum')->group(function () {
    // Units and Lessons
    Route::get('/units', [LearningController::class, 'getUnits']);
    Route::get('/units/{unit}/lessons', [LearningController::class, 'getUnitLessons']);
    Route::get('/lessons/{lesson}/content', [LearningController::class, 'getLessonContent']);
    Route::post('/lessons/{lesson}/complete', [LearningController::class, 'completeLesson']);

    // Exercise routes
    Route::get('/exercises/{exercise}', [ExerciseController::class, 'getExercise']);
    Route::post('/exercises/{exercise}/submit', [ExerciseController::class, 'submitAnswer']);
    Route::get('/exercises/{exercise}/hint', [ExerciseController::class, 'getHint']);
    Route::get('/exercises/practice', [ExerciseController::class, 'getPracticeExercises']);

    // Practice
    Route::post('/units/{unit}/practice', [LearningController::class, 'practiceUnit']);

    // Progress
    Route::get('/user/progress', [LearningController::class, 'getUserProgress']);
});
