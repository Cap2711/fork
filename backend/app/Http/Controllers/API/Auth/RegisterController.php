<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends BaseAPIController
{
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
                return $this->sendError('Invalid invite', ['invite_token' => 'Invalid or expired invite token.']);
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

        return $this->sendCreatedResponse([
            'token' => $token,
            'user' => $user,
        ], 'Successfully registered');
    }
}