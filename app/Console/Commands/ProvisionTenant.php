<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProvisionTenant extends Command
{
    protected $signature = 'tenant:provision
        {name : Pharmacy/company name}
        {slug : Unique tenant slug}
        {domain : Exact customer-facing domain}
        {database : Existing or new tenant database name}
        {--db-host=127.0.0.1 : Tenant database host}
        {--db-port=3306 : Tenant database port}
        {--db-user= : Tenant database username}
        {--db-password= : Tenant database password}
        {--skip-create-db : Do not issue CREATE DATABASE}
        {--skip-migrate : Do not run tenant migrations}
        {--verify-domain : Activate the domain immediately (only after DNS and HTTPS are configured)}';

    protected $description = 'Provision an isolated MediStore company database and domain.';

    public function handle(): int
    {
        if (config('tenancy.mode') !== 'saas') {
            $this->error('Set TENANCY_MODE=saas before provisioning tenants.');
            return self::FAILURE;
        }

        $name = trim($this->argument('name'));
        $slug = strtolower(trim($this->argument('slug')));
        $domain = strtolower(rtrim(trim($this->argument('domain')), '.'));
        $database = trim($this->argument('database'));
        $dbUser = trim((string) $this->option('db-user'));
        $dbPassword = (string) $this->option('db-password');
        if ($dbPassword === '') $dbPassword = (string) $this->secret('Tenant database password');
        if ($dbPassword === '') { $this->error('A non-empty tenant database password is required.'); return self::FAILURE; }

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,98}[a-z0-9]$/', $slug)
            || ! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $database)
            || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || ! $dbUser) {
            $this->error('Invalid slug, database, domain or database username.');
            return self::FAILURE;
        }

        $central = DB::connection('central');
        if ($central->table('tenant_companies')->where('slug', $slug)->exists()
            || $central->table('tenant_domains')->where('domain', $domain)->exists()
            || $central->table('tenant_companies')->where('database_name', $database)->exists()) {
            $this->error('Tenant slug, domain or database name is already registered.');
            return self::FAILURE;
        }

        try {
            if (! $this->option('skip-create-db')) {
                $central->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $tenantId = $central->table('tenant_companies')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'status' => 'provisioning',
                'database_driver' => 'mysql',
                'database_host' => (string) $this->option('db-host'),
                'database_port' => (int) $this->option('db-port'),
                'database_name' => $database,
                'database_username' => $dbUser,
                'database_password_encrypted' => Crypt::encryptString($dbPassword),
                'metadata' => json_encode(['created_by_command' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $central->table('tenant_domains')->insert([
                'tenant_company_id' => $tenantId,
                'domain' => $domain,
                'is_primary' => true,
                'is_verified' => (bool) $this->option('verify-domain'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $this->option('skip-migrate')) {
                config(['database.connections.tenant' => [
                    'driver' => 'mysql', 'host' => (string) $this->option('db-host'),
                    'port' => (int) $this->option('db-port'), 'database' => $database,
                    'username' => $dbUser, 'password' => $dbPassword, 'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true,
                    'strict' => true, 'engine' => null,
                ]]);
                DB::purge('tenant');
                $exit = Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);
                $this->line(Artisan::output());
                if ($exit !== 0) throw new RuntimeException('Tenant migration failed.');
            }

            $central->table('tenant_companies')->where('id', $tenantId)->update([
                'status' => 'active', 'provisioned_at' => now(), 'updated_at' => now(),
            ]);
            $this->info("Tenant ID: {$tenantId}. Tenant {$slug} provisioned. Domain: {$domain}. Database: {$database}.");
            if (! $this->option('verify-domain')) $this->warn('Domain is disabled by default. After DNS and TLS are ready, run: php artisan tenant:verify-domain ' . $domain);
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Provisioning failed: ' . $e->getMessage());
            if (isset($tenantId)) $central->table('tenant_companies')->where('id', $tenantId)->update(['status' => 'suspended', 'updated_at' => now()]);
            return self::FAILURE;
        } finally {
            DB::purge('tenant');
            DB::setDefaultConnection(env('DB_CONNECTION', 'mysql'));
        }
    }
}
