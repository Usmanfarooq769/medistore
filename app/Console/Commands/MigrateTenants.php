<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate {--tenant= : Only migrate a specific tenant ID}';
    protected $description = 'Run application migrations separately for each active MediStore tenant database.';

    public function handle(): int
    {
        if (config('tenancy.mode') !== 'saas') {
            $this->error('TENANCY_MODE must be saas.');
            return self::FAILURE;
        }

        $query = DB::connection('central')->table('tenant_companies')->where('status', 'active');
        if ($id = $this->option('tenant')) $query->where('id', (int) $id);
        $tenants = $query->orderBy('id')->get();
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->info("Migrating tenant {$tenant->id}: {$tenant->name}");
            try {
                config(['database.connections.tenant' => [
                    'driver' => $tenant->database_driver,
                    'host' => $tenant->database_host,
                    'port' => $tenant->database_port,
                    'database' => $tenant->database_name,
                    'username' => $tenant->database_username,
                    'password' => $tenant->database_password_encrypted ? Crypt::decryptString($tenant->database_password_encrypted) : '',
                    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true,
                    'strict' => true, 'engine' => null,
                ]]);
                DB::purge('tenant');
                $exit = Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);
                $this->line(Artisan::output());
                if ($exit !== 0) $failed++;
            } catch (Throwable $e) {
                report($e);
                $this->error("Tenant {$tenant->id} failed: {$e->getMessage()}");
                $failed++;
            } finally {
                DB::purge('tenant');
            }
        }

        DB::setDefaultConnection(env('DB_CONNECTION', 'mysql'));
        $this->info('Tenant migrations completed. Failed tenants: ' . $failed);
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
