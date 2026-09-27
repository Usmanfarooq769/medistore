<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place where stock changes. Always call inside DB::transaction().
 *   medicine_batches.quantity  = real stock per batch
 *   medicines.total_stock      = cached total (fast lists / alerts)
 *   stock_movements            = ledger of every change
 */
class StockService
{
    /** Stock IN – new batch (purchase) */
    public function addBatch(Medicine $medicine, array $data, string $reference, string $type = 'purchase'): MedicineBatch
    {
        $qty = (int) $data['quantity'];

        $batch = MedicineBatch::create([
            'medicine_id'      => $medicine->id,
            'purchase_id'      => $data['purchase_id'] ?? null,
            'supplier_id'      => $data['supplier_id'] ?? null,
            'batch_no'         => $data['batch_no'] ?? null,
            'expiry_date'      => $data['expiry_date'] ?? null,
            'purchase_price'   => $data['purchase_price'] ?? $medicine->purchase_price,
            'sale_price'       => $data['sale_price'] ?? $medicine->sale_price,
            'initial_quantity' => $qty,
            'quantity'         => $qty,
        ]);

        $this->log($medicine, $qty, $batch->id, $type, $reference, $data['note'] ?? null);

        return $batch;
    }

    /** Stock OUT for sale – FEFO (first expiry first out), expired batches skipped */
    public function deductFefo(Medicine $medicine, int $qty, string $reference): array
    {
        $batches = $medicine->sellableBatches()->lockForUpdate()->get();
        $available = (int) $batches->sum('quantity');

        if ($available < $qty) {
            throw ValidationException::withMessages([
                'items' => "Insufficient stock for {$medicine->display_name}. Available (non-expired): {$available}",
            ]);
        }

        $allocations = [];
        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch->quantity, $remaining);
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $allocations[] = [$batch, $take];
            $this->log($medicine, -$take, $batch->id, 'sale', $reference);
        }

        return $allocations;
    }

    /**
     * Change a specific batch by +delta / -delta
     * used by: customer return (+), supplier return (-), adjustments (+/-), sale void (+)
     */
    public function moveBatch(MedicineBatch $batch, int $delta, string $type, string $reference, ?string $note = null): void
    {
        $medicine = Medicine::lockForUpdate()->findOrFail($batch->medicine_id);

        if ($delta < 0 && $batch->quantity < abs($delta)) {
            throw ValidationException::withMessages([
                'quantity' => "Batch {$batch->batch_no} of {$medicine->display_name} has only {$batch->quantity} unit(s).",
            ]);
        }

        $batch->increment('quantity', $delta);
        $this->log($medicine, $delta, $batch->id, $type, $reference, $note);
    }

    /** Put stock back (sale void / return) – recreates a batch if original was deleted */
    public function restore(int $medicineId, ?int $batchId, int $qty, string $type, string $reference, ?string $note = null): void
    {
        $batch = $batchId ? MedicineBatch::lockForUpdate()->find($batchId) : null;

        if (! $batch) {
            $medicine = Medicine::findOrFail($medicineId);
            $batch = MedicineBatch::create([
                'medicine_id' => $medicine->id, 'batch_no' => 'RETURN', 'purchase_price' => $medicine->purchase_price,
                'sale_price' => $medicine->sale_price, 'initial_quantity' => 0, 'quantity' => 0,
            ]);
        }

        $this->moveBatch($batch, $qty, $type, $reference, $note);
    }

    /** Remove whole batch (purchase void) */
    public function removeBatch(MedicineBatch $batch, string $reference): void
    {
        if ($batch->quantity > 0) {
            $this->moveBatch($batch, -$batch->quantity, 'purchase_void', $reference, "Batch {$batch->batch_no} removed");
        }
        $batch->delete();
    }

    private function log(Medicine $medicine, int $delta, ?int $batchId, string $type, string $reference, ?string $note = null): void
    {
        $medicine->total_stock = (int) $medicine->total_stock + $delta;
        $medicine->saveQuietly();

        StockMovement::create([
            'medicine_id'       => $medicine->id,
            'medicine_batch_id' => $batchId,
            'type'              => $type,
            'quantity'          => $delta,
            'balance_after'     => $medicine->total_stock,
            'reference'         => $reference,
            'note'              => $note,
            'user_id'           => auth()->id(),
        ]);
    }

    /** Clear cached dashboard / alert numbers after any stock change */
    public static function flushCache(): void
    {
        Cache::forget('alerts.counts');
        Cache::forget('dashboard.stats.' . today()->toDateString());
    }
}
