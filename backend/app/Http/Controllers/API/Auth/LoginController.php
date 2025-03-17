<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends BaseAPIController
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->sendUnauthorizedResponse('Invalid credentials');
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'user' => $user,
        ], 'Successfully logged in');
    }

    public function register(Request $request)
    {
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
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Successfully logged out');
    }
}