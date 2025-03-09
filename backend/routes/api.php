<?php

use App\Http\Controllers\Auth\AdminInviteController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('api')->group(function () {
    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('register', [LoginController::class, 'register']);
        Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

        // Google OAuth Routes
        Route::get('google', [GoogleController::class, 'redirectToGoogle']);
        Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback']);
    });

    // Admin Routes (protected by admin middleware in controller)
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::post('invite', [AdminInviteController::class, 'invite']);
        Route::get('invites', [AdminInviteController::class, 'listInvites']);
        Route::post('invite/validate', [AdminInviteController::class, 'validateInvite']);
    });
});