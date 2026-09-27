<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('role:admin') */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'You are not allowed to perform this action.'], 403);
            }
            abort(403, 'You are not allowed to access this page.');
        }

        return $next($request);
    }
}
