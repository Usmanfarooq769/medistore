<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\FbrService;
use Illuminate\Console\Command;

class FbrSyncPending extends Command
{
    protected $signature = 'store:fbr-sync {--limit=100}';
    protected $description = 'Retry FBR invoice submission for sales whose status is pending';

    public function handle(FbrService $fbr): int
    {
        if (! $fbr->enabled()) { $this->info('FBR integration is disabled.'); return self::SUCCESS; }
        $sales = Sale::where('fbr_status', 'pending')->orderBy('id')->limit((int) $this->option('limit'))->get();
        foreach ($sales as $sale) { $fbr->issue($sale); $this->line($sale->invoice_no . ' -> ' . $sale->fresh()->fbr_status); }
        $this->info($sales->count() . ' invoice(s) processed.');
        return self::SUCCESS;
    }
}
