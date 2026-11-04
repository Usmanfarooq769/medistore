<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batch-level stock reservations for online orders.
 * Reservations do not alter physical stock or the stock ledger.
 * All allocation happens under row locks to prevent overselling.
 */
class OnlineOrderInventoryService
{
    public function reserve(int $orderId, int $branchId, array $items, int $minutes = 30): void
    {
        foreach ($items as $item) {
            $remaining = (int) $item['quantity'];

            $batches = DB::table('medicine_batches')
                ->where('medicine_id', (int) $item['medicine_id'])
                ->where('branch_id', $branchId)
                ->where('quantity', '>', 0)
                ->where('is_quarantined', false)
                ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) break;

                $reserved = (int) DB::table('online_order_reservations')
                    ->where('medicine_batch_id', $batch->id)
                    ->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->sum('quantity');

                $free = max(0, (int) $batch->quantity - $reserved);
                if ($free < 1) continue;

                $take = min($free, $remaining);
                DB::table('online_order_reservations')->insert([
                    'online_order_id' => $orderId,
                    'medicine_batch_id' => $batch->id,
                    'quantity' => $take,
                    'status' => 'active',
                    'expires_at' => now()->addMinutes($minutes),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'items' => "Not enough unreserved stock for medicine ID {$item['medicine_id']}. Please refresh the catalogue.",
                ]);
            }
        }
    }

    public function release(int $orderId): void
    {
        DB::table('online_order_reservations')
            ->where('online_order_id', $orderId)
            ->where('status', 'active')
            ->update(['status' => 'released', 'updated_at' => now()]);
    }

    public function extend(int $orderId): void
    {
        DB::table('online_order_reservations')
            ->where('online_order_id', $orderId)
            ->where('status', 'active')
            ->update(['expires_at' => null, 'updated_at' => now()]);
    }

    public function expire(): int
    {
        return DB::table('online_order_reservations')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'released', 'updated_at' => now()]);
    }
}
