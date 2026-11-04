<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPortalAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        if (! $plain) return response()->json(['message' => 'Customer portal authentication required.'], 401);
        $token = DB::table('customer_portal_tokens')->where('token_hash', hash('sha256', $plain))->where('expires_at', '>', now())->first();
        if (! $token) return response()->json(['message' => 'Customer portal session expired. Please sign in again.'], 401);
        $account = DB::table('customer_portal_accounts')->where('id', $token->account_id)->where('status', 'active')->first();
        if (! $account) return response()->json(['message' => 'Customer portal account is inactive.'], 403);
        DB::table('customer_portal_tokens')->where('id', $token->id)->update(['last_used_at' => now()]);
        $request->attributes->set('customer_portal_account', $account);
        return $next($request);
    }
}
