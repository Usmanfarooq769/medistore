<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TenantConnectionManager
{
    private ?string $originalCachePrefix = null;
    private ?string $originalDefaultConnection = null;
    private array $originalDiskRoots = [];

    public function activate(object $tenant): void
    {
        $this->originalCachePrefix ??= (string) config('cache.prefix', 'laravel_cache');
        $this->originalDefaultConnection ??= (string) config('database.default', env('DB_CONNECTION', 'mysql'));

        if (($tenant->database_driver ?? 'mysql') !== 'mysql') {
            throw new RuntimeException('Only MySQL tenant databases are supported by this release.');
        }

        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'url' => null,
                'host' => $tenant->database_host,
                'port' => (int) $tenant->database_port,
                'database' => $tenant->database_name,
                'username' => $tenant->database_username,
                'password' => $tenant->database_password_encrypted
                    ? Crypt::decryptString($tenant->database_password_encrypted)
                    : '',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ],
            'database.default' => 'tenant',
        ]);

        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('tenant')->getPdo(); // fail fast before any controller or auth query runs

        // Prefix cache keys so settings, dashboard counts and lookup caches cannot cross tenants.
        config(['cache.prefix' => $this->originalCachePrefix . '_tenant_' . $tenant->id]);
        Cache::purge();

        // Keep private local uploads physically separated by tenant.
        foreach (['local' => 'private'] as $disk => $folder) {
            $this->originalDiskRoots[$disk] ??= config('filesystems.disks.' . $disk . '.root');
            $root = storage_path('app/tenants/' . $tenant->id . '/' . $folder);
            if (! is_dir($root)) mkdir($root, 0775, true);
            config(['filesystems.disks.' . $disk . '.root' => $root]);
            Storage::forgetDisk($disk);
        }

        app(TenantContext::class)->set($tenant);
    }

    public function isActive(): bool
    {
        return $this->originalDefaultConnection !== null;
    }

    public function restore(): void
    {
        DB::purge('tenant');
        DB::setDefaultConnection($this->originalDefaultConnection ?? env('DB_CONNECTION', 'mysql'));
        config(['database.default' => $this->originalDefaultConnection ?? env('DB_CONNECTION', 'mysql')]);
        config(['cache.prefix' => $this->originalCachePrefix ?? env('CACHE_PREFIX', 'laravel_cache')]);
        Cache::purge();
        foreach ($this->originalDiskRoots as $disk => $root) {
            config(['filesystems.disks.' . $disk . '.root' => $root]);
            Storage::forgetDisk($disk);
        }
        $this->originalDiskRoots = [];
        app(TenantContext::class)->clear();
        $this->originalCachePrefix = null;
        $this->originalDefaultConnection = null;
    }
}
