<?php

namespace App\Console\Commands;

use App\Services\OnlineOrderInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOnlineOrderReservations extends Command
{
    protected $signature = 'store:expire-order-reservations';
    protected $description = 'Release expired online-order stock reservations';

    public function handle(OnlineOrderInventoryService $inventory): int
    {
        $released = $inventory->expire();

        // An unconfirmed request whose hold expired must not remain confirmable.
        DB::table('online_orders')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('online_order_reservations as r')
                    ->whereColumn('r.online_order_id', 'online_orders.id')
                    ->where('r.status', 'active');
            })
            ->update(['status' => 'cancelled', 'staff_note' => 'Automatically cancelled because the stock reservation expired.', 'updated_at' => now()]);

        $this->info("Released {$released} expired reservation row(s).");
        return self::SUCCESS;
    }
}
