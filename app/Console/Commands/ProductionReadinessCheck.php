<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TenantConnectionManager;
use Throwable;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'app:readiness-check';
    protected $description = 'Run basic deployment configuration checks. This is not a substitute for UAT or a security audit.';

    public function handle(TenantConnectionManager $connections): int
    {
        $failures = 0;
        $warnings = 0;
        $check = function (string $label, bool $ok, string $detail = '', string $severity = 'fail') use (&$failures, &$warnings) {
            if ($ok) {
                $this->info('[PASS] ' . $label . ($detail ? ' — ' . $detail : ''));
                return;
            }
            if ($severity === 'warn') {
                $warnings++;
                $this->warn('[WARN] ' . $label . ($detail ? ' — ' . $detail : ''));
            } else {
                $failures++;
                $this->error('[FAIL] ' . $label . ($detail ? ' — ' . $detail : ''));
            }
        };

        $check('Production environment', app()->environment('production'), 'APP_ENV=' . app()->environment());
        $check('Debug disabled', ! (bool) config('app.debug'), 'APP_DEBUG=' . (config('app.debug') ? 'true' : 'false'));
        $check('Application key configured', filled(config('app.key')));
        $check('HTTPS application URL', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'));
        $check('Secure session cookies', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE must be true');
        $check('Queue is asynchronous', config('queue.default') !== 'sync', 'QUEUE_CONNECTION=' . config('queue.default'));
        $check('Cache driver is not array', config('cache.default') !== 'array', 'CACHE_STORE=' . config('cache.default'));

        try {
            DB::connection()->select('SELECT 1');
            $check('Database connectivity', true, DB::getDefaultConnection());
        } catch (Throwable $e) {
            $check('Database connectivity', false, $e->getMessage());
        }
        $check('Storage writable', is_writable(storage_path()) && is_writable(storage_path('framework')));
        $check('Bootstrap cache writable', is_writable(base_path('bootstrap/cache')));

        if (config('tenancy.mode') === 'saas') {
            try {
                $tenants = DB::connection('central')->table('tenant_companies')->where('status', 'active')->orderBy('id')->get();
                $unverified = DB::connection('central')->table('tenant_domains')->where('is_verified', false)->count();
                $check('Central tenant registry', true, $tenants->count() . ' active tenant(s)');
                $check('At least one active tenant', $tenants->isNotEmpty(), 'No active tenants are registered.');
                $check('Tenant domains verified', $unverified === 0, $unverified . ' unverified domain(s)', 'warn');
                $tenantFailures = [];
                foreach ($tenants as $tenant) {
                    try {
                        $connections->activate($tenant);
                        $hasMigrations = DB::connection('tenant')->getSchemaBuilder()->hasTable('migrations');
                        if (! $hasMigrations) $tenantFailures[] = $tenant->slug . ': migrations table missing';
                    } catch (Throwable $tenantError) {
                        $tenantFailures[] = $tenant->slug . ': ' . $tenantError->getMessage();
                    } finally {
                        $connections->restore();
                    }
                }
                $check('Tenant databases reachable and migrated', count($tenantFailures) === 0, $tenantFailures ? implode('; ', $tenantFailures) : 'All active tenant DBs responded.');
            } catch (Throwable $e) {
                $check('Central tenant registry', false, $e->getMessage());
            }
        } else {
            $check('Single-company mode', true, 'Existing DB is used as the pharmacy database.');
        }

        $this->newLine();
        $this->line("Failures: {$failures}; warnings: {$warnings}");
        $this->warn('Passing this command does NOT certify production readiness. Complete the release checklist and full UAT.');
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
