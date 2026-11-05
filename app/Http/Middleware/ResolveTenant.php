<?php

namespace App\Http\Middleware;

use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ResolveTenant
{
    public function __construct(private readonly TenantConnectionManager $connections) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('tenancy.mode', 'single') !== 'saas') {
            return $next($request);
        }

        $domain = strtolower(rtrim($request->getHost(), '.'));
        if (! $domain || in_array($domain, config('tenancy.central_domains', []), true)) {
            return response()->json(['message' => 'A registered pharmacy domain is required.'], 404);
        }

        $tenant = DB::connection(config('tenancy.central_connection', 'central'))
            ->table('tenant_domains as d')
            ->join('tenant_companies as c', 'c.id', '=', 'd.tenant_company_id')
            ->where('d.domain', $domain)
            ->where('d.is_verified', true)
            ->select('c.*')
            ->first();

        if (! $tenant) {
            return response()->json(['message' => 'This pharmacy domain is not registered.'], 404);
        }
        if ($tenant->status !== 'active') {
            return response()->json(['message' => 'This pharmacy account is currently unavailable. Please contact support.'], 403);
        }

        try {
            $this->connections->activate($tenant);
        } catch (Throwable $e) {
            report($e);
            $this->connections->restore();
            return response()->json(['message' => 'The pharmacy database is temporarily unavailable.'], 503);
        }

        $request->attributes->set('tenant_company', $tenant);
        try {
            return $next($request);
        } finally {
            $this->connections->restore();
        }
    }
}
