<?php

namespace App\Console\Commands;

use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class RestoreDatabaseFromCloud extends Command
{
    protected $signature = 'store:restore-cloud {path : Exact encrypted object path in the backup disk} {--force : Confirm destructive database replacement} {--tenant= : Tenant ID (required in SaaS mode)}';
    protected $description = 'Restore an encrypted MediStore database backup. This replaces the selected database.';

    public function handle(TenantConnectionManager $connections): int
    {
        if (! $this->option('force') && ! $this->confirm('This replaces the current database. Have you backed it up? Continue?')) return self::FAILURE;
        $tenantMode = config('tenancy.mode') === 'saas';
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $tmp = null;

        try {
            if ($tenantMode) {
                if (! $tenantId) throw new \RuntimeException('In SaaS mode, pass --tenant=ID to select the database to restore.');
                $tenant = DB::connection('central')->table('tenant_companies')->where('id', $tenantId)->first();
                if (! $tenant) throw new \RuntimeException('Tenant not found.');
                $connections->activate($tenant);
                $expectedPrefix = 'tenants/' . $tenantId . '/medistore-backups/';
            } else {
                $expectedPrefix = 'medistore-backups/';
            }

            $path = (string) $this->argument('path');
            if (! str_starts_with($path, $expectedPrefix)) throw new \RuntimeException('Backup path does not belong to the selected installation/tenant.');
            $payload = Storage::disk(env('BACKUP_DISK', 's3'))->get($path);
            $prefix = "MEDISTORE-ENC-1\0";
            if (! str_starts_with($payload, $prefix)) throw new \RuntimeException('Unknown backup format.');
            $offset = strlen($prefix);
            $iv = substr($payload, $offset, 12);
            $tag = substr($payload, $offset + 12, 16);
            $cipher = substr($payload, $offset + 28);
            $key = hash('sha256', (string) config('app.key'), true);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) throw new \RuntimeException('Decryption failed. Confirm the same APP_KEY is configured.');

            $connection = config('database.default');
            $cfg = config('database.connections.' . $connection);
            $driver = $cfg['driver'] ?? $connection;
            $tmp = storage_path('app/tenant-tmp/' . ($tenantId ? $tenantId . '/' : 'single/') . 'restore-' . now()->format('YmdHis') . '.sql');
            if (! is_dir(dirname($tmp))) mkdir(dirname($tmp), 0700, true);
            file_put_contents($tmp, $plain);
            @chmod($tmp, 0600);

            if ($driver === 'sqlite') {
                $dest = $cfg['database'];
                $dest = str_starts_with($dest, '/') ? $dest : base_path($dest);
                if (! copy($tmp, $dest)) throw new \RuntimeException('Could not replace the SQLite database file. Stop application workers and retry.');
            } elseif ($driver === 'mysql') {
                $process = new Process(['mysql', '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username'], (string) $cfg['database']], base_path(), ['MYSQL_PWD' => (string) $cfg['password']]);
                $process->setInput($plain);
                $process->setTimeout(1200);
                $process->run();
                if (! $process->isSuccessful()) throw new \RuntimeException('mysql restore failed: ' . $process->getErrorOutput());
            } else {
                throw new \RuntimeException('Restore supports MySQL and SQLite only.');
            }

            $this->info('Database restore completed. Clear caches and verify the application before reopening sales.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if ($tmp && is_file($tmp)) @unlink($tmp);
            if ($connections->isActive()) $connections->restore();
        }
    }
}
