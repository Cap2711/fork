<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends BaseAPIController
{
    /**
     * List of allowed email domains
     * @var array
     */
    protected $allowedDomains = [
        'example.com', // TODO: Replace with actual allowed domains
    ];

    public function redirectToGoogle()
    {
        try {
            $redirectUrl = Socialite::driver('google')
                ->redirect()
                ->getTargetUrl();

            return $this->sendResponse([
                'url' => $redirectUrl
            ]);
        } catch (Exception $e) {
            return $this->sendError('Failed to initiate Google login', ['error' => $e->getMessage()], 500);
        }
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->user();

            // Validate email domain
            $emailDomain = explode('@', $googleUser->getEmail())[1] ?? null;
            if (!$emailDomain || !in_array($emailDomain, $this->allowedDomains)) {
                return $this->sendError(
                    'Unauthorized email domain',
                    ['email' => 'This email domain is not authorized to access the application.'],
                    403
                );
            }

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // Check if email already exists
                $existingUser = User::where('email', $googleUser->getEmail())->first();
                
                if ($existingUser) {
                    // Update existing user with Google credentials
                    $existingUser->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar() ?? null,
                    ]);
                    $user = $existingUser->fresh();
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar() ?? null,
                        'password' => bcrypt(Str::random(16)), // Random password as it's not needed for OAuth
                        'email_verified_at' => now(),
                        'role' => 'user',
                    ]);
                }
            } else {
                // Update existing Google user's avatar if changed
                $user->update([
                    'avatar' => $googleUser->getAvatar() ?? $user->avatar,
                ]);
                $user = $user->fresh();
            }

            // Generate token for API authentication
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'user' => $user,
            ], 'Successfully logged in with Google');

        } catch (InvalidStateException $e) {
            return $this->sendError('Invalid OAuth state', ['error' => 'Please try logging in again.'], 401);
        } catch (Exception $e) {
            return $this->sendError('Google login failed', ['error' => $e->getMessage()], 500);
        }
    }
}