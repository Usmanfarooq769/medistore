<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyTenantDomain extends Command
{
    protected $signature = 'tenant:verify-domain {domain : Exact domain to activate}';
    protected $description = 'Mark a tenant domain as verified after DNS and TLS have been configured.';

    public function handle(): int
    {
        $domain = strtolower(rtrim(trim($this->argument('domain')), '.'));
        $registry = DB::connection('central')->table('tenant_domains');
        if (! $registry->where('domain', $domain)->exists()) {
            $this->error('Domain not found.');
            return self::FAILURE;
        }
        $registry->where('domain', $domain)->update(['is_verified' => true, 'updated_at' => now()]);
        $this->info("Tenant domain {$domain} is now active.");
        return self::SUCCESS;
    }
}
