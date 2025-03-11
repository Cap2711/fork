<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AdminInviteController extends BaseAPIController
{
    /**
     * Create a new admin invite
     */
    public function create(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if the email already exists as an admin
        $existingAdmin = User::where('email', $request->email)
            ->where('role', 'admin')
            ->exists();

        if ($existingAdmin) {
            return $this->sendError('User is already an admin');
        }

        // Check if there's an existing invite for this email
        $existingInvite = AdminInvite::where('email', $request->email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvite) {
            $inviteUrl = URL::to('/register?invite=' . $existingInvite->token . '&email=' . urlencode($request->email));
            
            return $this->sendResponse([
                'invite_url' => $inviteUrl,
            ], 'Existing invite has been retrieved');
        }

        // Create a new invite
        $invite = AdminInvite::create([
            'email' => $request->email,
            'token' => Str::random(32),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7), // Expires in 7 days
        ]);

        $inviteUrl = URL::to('/register?invite=' . $invite->token . '&email=' . urlencode($request->email));

        return $this->sendCreatedResponse([
            'invite_url' => $inviteUrl,
        ], 'Admin invite has been created');
    }

    /**
     * Validate an invite token
     */
    public function validate(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $invite = AdminInvite::where('token', $request->token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invite) {
            return $this->sendError('Invalid or expired invite token');
        }

        return $this->sendResponse([
            'email' => $invite->email,
            'expires_at' => $invite->expires_at,
        ], 'Invite is valid');
    }

    /**
     * List all invites for the admin dashboard
     */
    public function index(Request $request)
    {
        $invites = AdminInvite::with('inviter')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse([
            'invites' => $invites,
        ], 'Admin invites retrieved successfully');
    }
}