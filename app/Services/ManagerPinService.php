<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Manager PIN override: a cashier/pharmacist can complete a restricted action
 * (discount above limit, void) if a manager/admin types their PIN.
 * Wrong PINs are rate-limited per requesting user.
 */
class ManagerPinService
{
    public const MAX_ATTEMPTS = 5;

    public function approver(?string $pin, string $permission, ?User $requester = null): ?User
    {
        $pin = trim((string) $pin);
        if ($pin === '') return null;

        $key = 'pin-attempts.' . ($requester?->id ?? request()->ip());
        if ((int) Cache::get($key, 0) >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['manager_pin' => 'Too many wrong PIN attempts. Try again in 10 minutes.']);
        }

        $candidates = User::where('is_active', true)->whereNotNull('manager_pin')->get();
        foreach ($candidates as $user) {
            if (Hash::check($pin, $user->manager_pin) && $user->hasPermission($permission)) {
                Cache::forget($key);
                return $user;
            }
        }

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addMinutes(10));
        return null;
    }
}
