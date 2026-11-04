<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Services\BranchContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'branch_id'        => $data['branch_id'] ?? BranchContext::id(),
            'purchase_id'      => $data['purchase_id'] ?? null,
            'supplier_id'      => $data['supplier_id'] ?? null,
            'batch_no'         => $data['batch_no'] ?? null,
            'expiry_date'      => $data['expiry_date'] ?? null,
            'purchase_price'   => $data['purchase_price'] ?? $medicine->purchase_price,
            'sale_price'       => $data['sale_price'] ?? $medicine->sale_price,
            'initial_quantity' => $qty,
            'quantity'         => $qty,
        ]);

        $this->log($medicine, $qty, $batch->id, $type, $reference, $data['note'] ?? null, (int) $batch->branch_id);
        if ($medicine->requires_controlled_tracking && Schema::hasTable('controlled_medicine_register')) {
            DB::table('controlled_medicine_register')->insert([
                'branch_id'=>$batch->branch_id,'medicine_id'=>$medicine->id,'batch_id'=>$batch->id,'transaction_type'=>'purchase',
                'quantity_change'=>$qty,'balance_after'=>$batch->quantity,'reference_no'=>$reference,'reason'=>'Stock received into controlled medicine inventory',
                'recorded_by'=>auth()->id(),'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        return $batch;
    }

    /** Stock OUT for sale – FEFO (first expiry first out), expired batches skipped */
    public function deductFefo(Medicine $medicine, int $qty, string $reference, ?int $reservationOrderId = null): array
    {
        if (! $medicine->stock_units_are_base && (int) $medicine->pack_size > 1) {
            throw ValidationException::withMessages(['items' => "Convert legacy pack stock to individual units for {$medicine->display_name} before selling."]);
        }
        $batches = $medicine->sellableBatches()->where('branch_id', BranchContext::id())->lockForUpdate()->get();
        $allocations = [];
        $remaining = $qty;

        // If this POS sale fulfils an online order, consume that order's reserved
        // batch allocations first. Other POS transactions cannot use these units.
        if ($reservationOrderId) {
            $reservedRows = DB::table('online_order_reservations as r')
                ->join('medicine_batches as b', 'b.id', '=', 'r.medicine_batch_id')
                ->where('r.online_order_id', $reservationOrderId)
                ->where('r.status', 'active')
                ->where('b.medicine_id', $medicine->id)
                ->where('b.branch_id', BranchContext::id())
                ->where('b.quantity', '>', 0)
                ->where('b.is_quarantined', false)
                ->where(fn ($q) => $q->whereNull('b.expiry_date')->orWhereDate('b.expiry_date', '>=', today()))
                ->orderByRaw('b.expiry_date IS NULL, b.expiry_date ASC')
                ->select('r.id as reservation_id', 'r.quantity as reserved_quantity', 'b.id as batch_id')
                ->lockForUpdate()->get();

            foreach ($reservedRows as $row) {
                if ($remaining <= 0) break;
                $batch = $batches->firstWhere('id', $row->batch_id);
                if (! $batch) continue;
                $take = min((int) $row->reserved_quantity, (int) $batch->quantity, $remaining);
                if ($take < 1) continue;
                $batch->decrement('quantity', $take);
                DB::table('online_order_reservations')->where('id', $row->reservation_id)->decrement('quantity', $take);
                DB::table('online_order_reservations')->where('id', $row->reservation_id)->where('quantity', '<=', 0)->delete();
                $this->reduceBinAllocations($batch->id, $take);
                $remaining -= $take;
                $allocations[] = [$batch, $take];
                $this->log($medicine, -$take, $batch->id, 'sale', $reference, 'Fulfilled reserved online order', (int) $batch->branch_id);
            }
        }

        // Normal counter sales can only use stock not reserved by an active order.
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $reserved = (int) DB::table('online_order_reservations')
                ->where('medicine_batch_id', $batch->id)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->sum('quantity');
            $free = max(0, (int) $batch->quantity - $reserved);
            $take = min($free, $remaining);
            if ($take < 1) continue;
            $batch->decrement('quantity', $take);
            $this->reduceBinAllocations($batch->id, $take);
            $remaining -= $take;
            $allocations[] = [$batch, $take];
            $this->log($medicine, -$take, $batch->id, 'sale', $reference, null, (int) $batch->branch_id);
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'items' => "Insufficient unreserved stock for {$medicine->display_name}. Please refresh available stock.",
            ]);
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
        if (! $medicine->stock_units_are_base && (int) $medicine->pack_size > 1) {
            throw ValidationException::withMessages(['quantity' => "Convert legacy pack stock to individual units for {$medicine->display_name} before changing stock."]);
        }

        if ($delta < 0) {
            $reserved = DB::table('online_order_reservations')
                ->where('medicine_batch_id', $batch->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->sum('quantity');
            $free = max(0, (int) $batch->quantity - (int) $reserved);
            if ($free < abs($delta)) {
                throw ValidationException::withMessages([
                    'quantity' => "Batch {$batch->batch_no} of {$medicine->display_name} has only {$free} unreserved unit(s).",
                ]);
            }
        }

        $batch->increment('quantity', $delta);
        if ($delta < 0) $this->reduceBinAllocations($batch->id, abs($delta));
        $this->log($medicine, $delta, $batch->id, $type, $reference, $note, (int) $batch->branch_id);
        if ($medicine->requires_controlled_tracking && Schema::hasTable('controlled_medicine_register')) {
            $registerType = match ($type) {
                'sale_return', 'purchase_return', 'sale_void', 'purchase_void' => 'return',
                'adjustment_in', 'adjustment_out' => 'adjustment',
                'branch_transfer_in' => 'transfer_in',
                'branch_transfer_out' => 'transfer_out',
                default => null,
            };
            if ($registerType) {
                DB::table('controlled_medicine_register')->insert([
                    'branch_id'=>$batch->branch_id,'medicine_id'=>$medicine->id,'batch_id'=>$batch->id,
                    'transaction_type'=>$registerType,'quantity_change'=>$delta,'balance_after'=>$batch->fresh()->quantity,
                    'reference_no'=>$reference,'reason'=>$note ?: 'Controlled medicine stock movement',
                    'recorded_by'=>auth()->id(),'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }
    }

    /** Put stock back (sale void / return) – recreates a batch if original was deleted */
    public function restore(int $medicineId, ?int $batchId, int $qty, string $type, string $reference, ?string $note = null): void
    {
        $batch = $batchId ? MedicineBatch::lockForUpdate()->find($batchId) : null;

        if (! $batch) {
            $medicine = Medicine::findOrFail($medicineId);
            $batch = MedicineBatch::create([
                'medicine_id' => $medicine->id, 'branch_id' => BranchContext::id(), 'batch_no' => 'RETURN', 'purchase_price' => $medicine->purchase_price,
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

    /** Keep bin allocations at or below the physical batch quantity when stock leaves a batch. */
    private function reduceBinAllocations(int $batchId, int $quantity): void
    {
        if ($quantity <= 0 || !Schema::hasTable('batch_location_stocks')) return;
        $remaining = $quantity;
        $allocations = DB::table('batch_location_stocks')->where('medicine_batch_id',$batchId)->where('quantity','>',0)->orderBy('id')->lockForUpdate()->get();
        foreach ($allocations as $allocation) {
            if ($remaining <= 0) break;
            $take = min((int)$allocation->quantity,$remaining);
            DB::table('batch_location_stocks')->where('id',$allocation->id)->decrement('quantity',$take);
            $remaining -= $take;
        }
    }

    private function log(Medicine $medicine, int $delta, ?int $batchId, string $type, string $reference, ?string $note = null, ?int $branchId = null): void
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
            'branch_id'         => $branchId ?? BranchContext::id(),
        ]);
    }

    /** Clear cached dashboard / alert numbers after any stock change */
    public static function flushCache(?array $branchIds = null): void
    {
        $branchIds = $branchIds ?: [BranchContext::id()];
        foreach (array_unique($branchIds) as $branchId) {
            Cache::forget('alerts.counts.' . $branchId);
            Cache::forget('dashboard.stats.' . $branchId . '.' . today()->toDateString());
        }
    }
}
