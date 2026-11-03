<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSupplierContracts extends Command
{
    protected $signature = 'store:expire-supplier-contracts';
    protected $description = 'Mark supplier contracts as expired after their end date';

    public function handle(): int
    {
        $count = DB::table('supplier_contracts')->where('status', 'active')->whereDate('ends_on', '<', today())->update(['status' => 'expired', 'updated_at' => now()]);
        $this->info("Expired {$count} supplier contract(s).");
        return self::SUCCESS;
    }
}
