<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class CreateTenantAdmin extends Command
{
    protected $signature = 'tenant:create-admin {--tenant= : Tenant ID in SaaS mode} {--name= : Administrator name} {--email= : Administrator email}';
    protected $description = 'Create the first secure administrator account without demo credentials.';

    public function handle(TenantConnectionManager $connections): int
    {
        try {
            if (config('tenancy.mode') === 'saas') {
                $tenantId = (int) $this->option('tenant');
                if (! $tenantId) throw new \RuntimeException('Pass --tenant=ID in SaaS mode.');
                $tenant = DB::connection('central')->table('tenant_companies')->where('id', $tenantId)->where('status', 'active')->first();
                if (! $tenant) throw new \RuntimeException('Active tenant not found.');
                $connections->activate($tenant);
            }

            $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
            $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Administrator email'))));
            $password = (string) $this->secret('Administrator password (minimum 12 characters)');
            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                throw new \RuntimeException('Provide a valid name/email and a password of at least 12 characters.');
            }
            if (User::where('email', $email)->exists()) throw new \RuntimeException('This email already exists in this pharmacy database.');
            $branch = Branch::where('code', 'MAIN')->first() ?? Branch::query()->orderBy('id')->first();
            if (! $branch) throw new \RuntimeException('No branch exists. Run tenant migrations first.');

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'branch_id' => $branch->id,
            ]);
            $this->info("Administrator {$email} created for branch {$branch->name}.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if ($connections->isActive()) $connections->restore();
        }
    }
}
