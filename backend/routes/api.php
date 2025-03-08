<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Traditional Authentication Routes
Route::post('auth/login', [LoginController::class, 'login']);
Route::post('auth/register', [LoginController::class, 'register']);
Route::middleware('auth:sanctum')->post('auth/logout', [LoginController::class, 'logout']);

// Google OAuth Routes
Route::get('auth/google', [GoogleController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);