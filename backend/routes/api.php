<?php

use App\Http\Controllers\Auth\AdminInviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [LoginController::class, 'register']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    
    Route::get('google', [GoogleController::class, 'redirectToGoogle']);
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback']);
});

// Admin Invite Routes (protected by admin middleware in controller)
Route::prefix('admin')->group(function () {
    Route::post('invite', [AdminInviteController::class, 'invite']);
    Route::get('invites', [AdminInviteController::class, 'listInvites']);
    Route::post('invite/validate', [AdminInviteController::class, 'validateInvite']);
});