<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\Auth\LogoutController;
use App\Http\Controllers\API\Auth\GoogleController;

Route::group([
    'prefix' => 'auth',
    'as' => 'auth.',
    'middleware' => 'api'
], function () {
    // Authentication routes
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);
    
    // Protected routes that require authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [LogoutController::class, 'logout']);
    });
    
    // Google OAuth routes
    Route::get('google', [GoogleController::class, 'redirectToGoogle']);
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback']);
});