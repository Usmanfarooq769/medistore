<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PartnerApiAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $key = (string) $request->header('X-Partner-Key');
        $secret = (string) $request->header('X-Partner-Secret');
        $partner = $key ? DB::table('partner_api_clients')->where('client_key', $key)->where('status', 'active')->first() : null;

        if (! $partner || ! $secret || ! Hash::check($secret, $partner->secret_hash)) {
            DB::table('partner_api_logs')->insert([
                'partner_id' => $partner?->id, 'method' => $request->method(), 'path' => $request->path(),
                'response_code' => 401, 'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
            return response()->json(['message' => 'Invalid partner credentials.'], 401);
        }

        $rateKey = 'partner-api:'.$partner->id.':'.$request->ip();
        $limit = max(1, (int) $partner->rate_limit_per_minute);
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return response()->json(['message' => 'Partner API rate limit exceeded.'], 429, ['Retry-After' => RateLimiter::availableIn($rateKey)]);
        }
        RateLimiter::hit($rateKey, 60);
        DB::table('partner_api_clients')->where('id', $partner->id)->update(['last_used_at' => now()]);
        $request->attributes->set('partner_api_client', $partner);
        $response = $next($request);
        DB::table('partner_api_logs')->insert([
            'partner_id' => $partner->id, 'method' => $request->method(), 'path' => $request->path(),
            'response_code' => $response->getStatusCode(), 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);
        return $response;
    }
}
