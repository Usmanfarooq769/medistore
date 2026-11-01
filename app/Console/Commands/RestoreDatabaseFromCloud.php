<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class RestoreDatabaseFromCloud extends Command
{
    protected $signature = 'store:restore-cloud {path : Exact encrypted object path in the backup disk} {--force : Confirm destructive database replacement}';
    protected $description = 'Restore a database backup encrypted by store:backup-cloud.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This replaces the current database. Have you backed it up? Continue?')) return self::FAILURE;
        $tmp = null;
        try {
            $path = $this->argument('path');
            if (!str_starts_with($path, 'medistore-backups/')) throw new \RuntimeException('Restore path must be inside medistore-backups/.');
            $payload = Storage::disk(env('BACKUP_DISK', 's3'))->get($path);
            $prefix = "MEDISTORE-ENC-1\0";
            if (!str_starts_with($payload, $prefix)) throw new \RuntimeException('Unknown backup format.');
            $offset = strlen($prefix);
            $iv = substr($payload, $offset, 12);
            $tag = substr($payload, $offset + 12, 16);
            $cipher = substr($payload, $offset + 28);
            $key = hash('sha256', (string) config('app.key'), true);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) throw new \RuntimeException('Decryption failed. Confirm the same APP_KEY is configured.');

            $cfg = config('database.connections.' . config('database.default'));
            $tmp = storage_path('app/private/restore-' . now()->format('YmdHis') . '.sql');
            if (!is_dir(dirname($tmp))) mkdir(dirname($tmp), 0700, true);
            file_put_contents($tmp, $plain);
            @chmod($tmp, 0600);

            if (config('database.default') === 'sqlite') {
                $dest = $cfg['database'];
                $dest = str_starts_with($dest, '/') ? $dest : base_path($dest);
                if (!copy($tmp, $dest)) throw new \RuntimeException('Could not replace the SQLite database file. Stop application workers and retry.');
            } elseif (config('database.default') === 'mysql') {
                $process = new Process(['mysql', '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username'], (string) $cfg['database']], base_path(), ['MYSQL_PWD' => (string) $cfg['password']]);
                $process->setInput($plain);
                $process->setTimeout(1200);
                $process->run();
                if (!$process->isSuccessful()) throw new \RuntimeException('mysql restore failed: ' . $process->getErrorOutput());
            } else {
                throw new \RuntimeException('Restore supports MySQL and SQLite only.');
            }

            $this->info('Database restore completed. Clear caches and verify the application before reopening sales.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if ($tmp && is_file($tmp)) @unlink($tmp);
        }
    }
}
