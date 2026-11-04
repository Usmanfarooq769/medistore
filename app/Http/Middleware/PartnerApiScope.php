<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PartnerApiScope
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        $partner = $request->attributes->get('partner_api_client');
        $scopes = $partner ? (json_decode($partner->scopes, true) ?: []) : [];
        if (! $partner || (! in_array('*', $scopes, true) && ! in_array($scope, $scopes, true))) {
            return response()->json(['message' => 'The partner API key does not have the required scope.'], 403);
        }
        return $next($request);
    }
}
