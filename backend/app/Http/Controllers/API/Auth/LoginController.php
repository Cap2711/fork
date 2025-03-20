<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoginController extends BaseAPIController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                Log::warning('Failed login attempt', ['email' => $request->email]);
                return $this->sendUnauthorizedResponse('Invalid credentials');
            }

            $user = User::where('email', $request->email)->firstOrFail();
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'user' => $user,
            ], 'Successfully logged in');
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            Log::error('User not found during login', ['email' => $request->email]);
            return $this->sendError('Authentication failed', ['email' => 'User not found'], 404);
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Login failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'invite_token' => 'nullable|string'
            ]);

            $role = 'user';

            // If invite token is present, validate it
            if ($request->invite_token) {
                $invite = AdminInvite::where('token', $request->invite_token)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->where('email', $request->email)
                    ->first();

                if (!$invite) {
                    Log::warning('Invalid invite token used', [
                        'email' => $request->email,
                        'token' => $request->invite_token
                    ]);
                    throw ValidationException::withMessages([
                        'invite_token' => ['Invalid or expired invite token.'],
                    ]);
                }

                $role = 'admin';
                $invite->markAsUsed();
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $role,
                'points' => 0
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'user' => $user,
            ], 'Successfully registered', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Registration error: ' . $e->getMessage(), [
                'data' => $request->except(['password', 'password_confirmation']),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Registration failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }
            
            $request->user()->currentAccessToken()->delete();
            return $this->sendResponse([], 'Successfully logged out');
        } catch (Exception $e) {
            Log::error('Logout error: ' . $e->getMessage(), [
                'user_id' => $request->user() ? $request->user()->id : null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}