<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends BaseAPIController
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                // Check if email already exists
                $existingUser = User::where('email', $googleUser->email)->first();
                
                if ($existingUser) {
                    // Update existing user with Google credentials
                    $existingUser->update([
                        'google_id' => $googleUser->id,
                        'avatar_url' => $googleUser->avatar,
                    ]);
                    $user = $existingUser;
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar_url' => $googleUser->avatar,
                        'password' => encrypt(rand(1,10000)), // Random password as it's not needed for OAuth
                        'email_verified_at' => now(),
                        'role' => 'user',
                    ]);
                }
            }

            Auth::login($user);

            // Generate token for API authentication
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'user' => $user,
            ], 'Successfully logged in with Google');

        } catch (Exception $e) {
            return $this->sendError('Google login failed', ['error' => $e->getMessage()], 500);
        }
    }
}