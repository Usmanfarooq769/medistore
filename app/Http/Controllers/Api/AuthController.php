<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Your account is deactivated.']);
        }

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
