<?php

namespace App\Console\Commands;

use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunTenantCommand extends Command
{
    protected $signature = 'tenants:run {command : Allowed tenant-aware scheduled command} {--tenant= : Run only for one tenant ID}';
    protected $description = 'Run an approved scheduled pharmacy command once for each active tenant.';

    private const ALLOWED = [
        'store:daily-report',
        'store:whatsapp-stock-alerts',
        'store:backup-cloud',
        'store:send-refill-reminders',
        'store:expire-supplier-contracts',
        'partners:deliver-webhooks',
        'system:health-check',
        'store:expire-order-reservations',
    ];

    public function handle(TenantConnectionManager $connections): int
    {
        $command = (string) $this->argument('command');
        if (! in_array($command, self::ALLOWED, true)) {
            $this->error('This command is not allowed to run through the tenant scheduler.');
            return self::FAILURE;
        }
        if (config('tenancy.mode') !== 'saas') {
            $this->error('TENANCY_MODE must be saas.');
            return self::FAILURE;
        }

        $query = DB::connection('central')->table('tenant_companies')->where('status', 'active')->orderBy('id');
        if ($tenantId = $this->option('tenant')) $query->where('id', (int) $tenantId);
        $tenants = $query->get();
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->line("[Tenant {$tenant->id}] {$tenant->name}: {$command}");
            try {
                $connections->activate($tenant);
                $exit = Artisan::call($command);
                $output = trim(Artisan::output());
                if ($output !== '') $this->line($output);
                if ($exit !== 0) $failed++;
            } catch (Throwable $e) {
                report($e);
                $this->error("Tenant {$tenant->id} failed: {$e->getMessage()}");
                $failed++;
            } finally {
                $connections->restore();
            }
        }

        $this->info('Tenant command finished. Tenants: ' . $tenants->count() . '; failures: ' . $failed);
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
