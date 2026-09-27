<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return UserResource::collection(
            User::when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
                ->orderBy('name')->paginate(15)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => ['required', Rule::in(['admin', 'cashier'])],
        ]);
        $user = User::create($data + ['is_active' => true]);

        return response()->json(['message' => 'User created.', 'data' => new UserResource($user)], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'email'     => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => 'nullable|string|min:6',
            'role'      => ['required', Rule::in(['admin', 'cashier'])],
            'is_active' => 'boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');

        abort_if($user->id === auth()->id() && (! $data['is_active'] || $data['role'] !== 'admin'), 422, 'You cannot deactivate or demote yourself.');
        $user->update($data);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'User updated.']);
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot delete yourself.');
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated.']);
    }
}
