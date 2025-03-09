<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AdminInviteController extends Controller
{
    public function __construct()
        {
            $this->middleware = [
                'auth:sanctum',
                function ($request, $next) {
                    if ($request->user()->role !== 'admin') {
                        return response()->json(['message' => 'Unauthorized'], 403);
                    }
                    return $next($request);
                }
            ];
        }

    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:admin_invites,email'
        ]);

        $invite = AdminInvite::create([
            'email' => $request->email,
            'token' => Str::random(32),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        // In a real app, you would send an email here with the invite link
        // For now, we'll just return the token in the response
        $inviteUrl = URL::to('/register?invite=' . $invite->token);

        return response()->json([
            'message' => 'Invite sent successfully',
            'invite_url' => $inviteUrl
        ]);
    }

    public function validateInvite(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $invite = AdminInvite::where('token', $request->token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invite) {
            return response()->json([
                'message' => 'Invalid or expired invite token'
            ], 400);
        }

        return response()->json([
            'message' => 'Valid invite token',
            'email' => $invite->email
        ]);
    }

    public function listInvites()
    {
        $invites = AdminInvite::with('inviter')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($invites);
    }
}