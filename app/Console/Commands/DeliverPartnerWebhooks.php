<?php

namespace App\Console\Commands;

use App\Services\PartnerWebhookService;
use Illuminate\Console\Command;

class DeliverPartnerWebhooks extends Command
{
    protected $signature = 'partners:deliver-webhooks {--limit=50}';
    protected $description = 'Deliver pending partner webhooks with signed payloads and retry handling.';

    public function handle(PartnerWebhookService $service): int
    {
        $result=$service->deliverPending((int)$this->option('limit'));
        $this->info("Delivered: {$result['delivered']}; failed/retried: {$result['failed']}.");
        return self::SUCCESS;
    }
}
