<?php

namespace App\Console\Commands;

use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Throwable;

class ImportTenantLocalFiles extends Command
{
    protected $signature = 'tenant:import-local-files {tenant : Tenant ID} {--overwrite : Replace files already present in the tenant folder}';
    protected $description = 'Copy the existing shared private local uploads into one tenant private storage root.';

    public function handle(TenantConnectionManager $connections): int
    {
        if (config('tenancy.mode') !== 'saas') {
            $this->error('TENANCY_MODE must be saas.');
            return self::FAILURE;
        }
        $tenantId = (int) $this->argument('tenant');
        $tenant = DB::connection('central')->table('tenant_companies')->where('id', $tenantId)->first();
        if (! $tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $source = storage_path('app/private');
        try {
            $connections->activate($tenant);
            $destination = config('filesystems.disks.local.root');
            if (! is_dir($source)) {
                $this->warn('No existing shared private storage folder found.');
                return self::SUCCESS;
            }
            $copied = 0;
            $skipped = 0;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile()) continue;
                $relative = substr($file->getPathname(), strlen($source) + 1);
                $target = $destination . DIRECTORY_SEPARATOR . $relative;
                if (is_file($target) && ! $this->option('overwrite')) { $skipped++; continue; }
                if (! is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
                if (! copy($file->getPathname(), $target)) throw new \RuntimeException('Failed to copy ' . $relative);
                @chmod($target, 0600);
                $copied++;
            }
            $this->info("Tenant {$tenantId}: copied {$copied} file(s); skipped {$skipped} existing file(s).");
            $this->warn('Source files were retained. Verify file downloads before archiving the old shared directory.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if ($connections->isActive()) $connections->restore();
        }
    }
}
