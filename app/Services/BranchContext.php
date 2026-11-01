<?php
namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    public static function id(): int
    {
        $user = auth()->user();
        // Administrators may switch the active branch using the validated request header.
        $requested = request()->header('X-Branch-ID');
        if ($user?->isAdmin() && $requested && ctype_digit((string) $requested) && Branch::whereKey((int) $requested)->where('is_active', true)->exists()) {
            return (int) $requested;
        }
        if ($user?->branch_id) return (int) $user->branch_id;
        return (int) (Branch::query()->where('code', 'MAIN')->value('id') ?? 1);
    }
}
