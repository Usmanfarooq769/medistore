<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\SecurityEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, SecurityEventLogger $securityEvents)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $securityEvents->record('login_failed', $request, $user?->id, $data['email']);
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        if (! $user->is_active) {
            $securityEvents->record('login_blocked_inactive', $request, $user->id, $data['email']);
            throw ValidationException::withMessages(['email' => 'Your account is deactivated.']);
        }
        $securityEvents->record('login_success', $request, $user->id, $user->email);

        return response()->json([
            'message'  => 'Welcome back, ' . $user->name . '!',
            'token'    => $user->createToken('spa')->plainTextToken,
            'user'     => new UserResource($user),
            'settings' => Setting::allCached(),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user'     => new UserResource($request->user()),
            'settings' => Setting::allCached(),
        ]);
    }

    public function logout(Request $request, SecurityEventLogger $securityEvents)
    {
        $securityEvents->record('logout', $request, $request->user()->id, $request->user()->email);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
