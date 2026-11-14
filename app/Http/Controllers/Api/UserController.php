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
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['admin', 'branch_manager', 'pharmacist', 'cashier'])],
            'branch_id' => 'required_if:role,branch_manager,pharmacist|nullable|exists:branches,id',
            'manager_pin' => 'nullable|digits_between:4,8',
            'permissions' => 'nullable|array',
        ]);
        $data['branch_id'] = $data['branch_id'] ?? 1;
        $pin = $data['manager_pin'] ?? null; unset($data['manager_pin']);
        $data['permissions'] = $this->cleanPermissions($data['permissions'] ?? []);
        $user = User::create($data + ['is_active' => true]);
        if ($pin) { $user->setManagerPin($pin); $user->save(); }

        return response()->json(['message' => 'User created.', 'data' => new UserResource($user)], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'email'     => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => 'nullable|string|min:8',
            'role'      => ['required', Rule::in(['admin', 'branch_manager', 'pharmacist', 'cashier'])],
            'is_active' => 'boolean',
            'branch_id' => 'required_if:role,branch_manager,pharmacist|nullable|exists:branches,id',
            'manager_pin' => 'nullable|digits_between:4,8',
            'clear_pin' => 'boolean',
            'permissions' => 'nullable|array',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $pin = $data['manager_pin'] ?? null; unset($data['manager_pin'], $data['clear_pin']);
        $data['permissions'] = $this->cleanPermissions($data['permissions'] ?? []);
        $data['is_active'] = $request->boolean('is_active');

        abort_if($user->id === auth()->id() && (! $data['is_active'] || $data['role'] !== 'admin'), 422, 'You cannot deactivate or demote yourself.');
        $user->update($data);
        if ($pin) { $user->setManagerPin($pin); $user->save(); }
        elseif ($request->boolean('clear_pin')) { $user->setManagerPin(null); $user->save(); }
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'User updated.']);
    }

    /** keep only known permission keys, as booleans */
    private function cleanPermissions(array $in): array
    {
        $known = array_keys(config('permissions.labels', []));
        $out = [];
        foreach ($in as $k => $v) if (in_array($k, $known, true)) $out[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        return $out;
    }

    public function permissionCatalog()
    {
        return response()->json(['labels' => config('permissions.labels'), 'roles' => config('permissions.roles')]);
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot delete yourself.');
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated.']);
    }
}
