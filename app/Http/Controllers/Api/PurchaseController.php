<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * STOCK IN: supplier bill -> batches created -> stock up -> supplier balance += due
 */
class PurchaseController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        return PurchaseResource::collection(
            Purchase::with('supplier:id,name')->withCount('items')
                ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('reference_no', 'like', "%{$s}%")->orWhere('supplier_invoice_no', 'like', "%{$s}%")))
                ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
                ->when($request->status, fn ($q, $v) => $q->where('payment_status', $v))
                ->when($request->from, fn ($q, $d) => $q->where('purchase_date', '>=', $d))
                ->when($request->to, fn ($q, $d) => $q->where('purchase_date', '<=', $d))
                ->latest('id')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(StorePurchaseRequest $request)
    {
        $data = $request->validated();

        $purchase = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['purchase_price']);
            $discount = min((float) ($data['discount'] ?? 0), $subtotal);
            $tax      = (float) ($data['tax'] ?? 0);
            $total    = round($subtotal - $discount + $tax, 2);
            $paid     = min((float) ($data['paid'] ?? 0), $total);

            $purchase = Purchase::create([
                'supplier_id'         => $data['supplier_id'],
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchase_date'       => $data['purchase_date'],
                'subtotal'            => $subtotal,
                'discount'            => $discount,
                'tax'                 => $tax,
                'total'               => $total,
                'paid'                => $paid,
                'due'                 => $total - $paid,
                'payment_status'      => payment_status($total, $paid),
                'note'                => $data['note'] ?? null,
                'user_id'             => auth()->id(),
            ]);
            $purchase->update(['reference_no' => 'PUR-' . now()->format('Ymd') . '-' . str_pad($purchase->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($data['items'] as $row) {
                $medicine = Medicine::lockForUpdate()->findOrFail($row['medicine_id']);
                $qty = (int) $row['quantity'] + (int) ($row['bonus_qty'] ?? 0);

                $batch = $this->stock->addBatch($medicine, [
                    'purchase_id'    => $purchase->id,
                    'supplier_id'    => $data['supplier_id'],
                    'batch_no'       => $row['batch_no'],
                    'expiry_date'    => $row['expiry_date'],
                    'purchase_price' => $row['purchase_price'],
                    'sale_price'     => $row['sale_price'],
                    'quantity'       => $qty,
                ], $purchase->reference_no);

                $purchase->items()->create([
                    'medicine_id'       => $medicine->id,
                    'medicine_batch_id' => $batch->id,
                    'batch_no'          => $row['batch_no'],
                    'expiry_date'       => $row['expiry_date'],
                    'quantity'          => $row['quantity'],
                    'bonus_qty'         => $row['bonus_qty'] ?? 0,
                    'purchase_price'    => $row['purchase_price'],
                    'sale_price'        => $row['sale_price'],
                    'total'             => round($row['quantity'] * $row['purchase_price'], 2),
                ]);

                $medicine->forceFill(['purchase_price' => $row['purchase_price'], 'sale_price' => $row['sale_price']])->saveQuietly();
            }

            if ($paid > 0) {
                Payment::create([
                    'party_type' => 'supplier', 'party_id' => $data['supplier_id'], 'purchase_id' => $purchase->id,
                    'amount' => $paid, 'method' => $data['payment_method'] ?? 'cash', 'payment_date' => $data['purchase_date'],
                    'note' => 'Paid with ' . $purchase->reference_no, 'user_id' => auth()->id(),
                ]);
            }

            Supplier::whereKey($data['supplier_id'])->increment('balance', $total - $paid);

            return $purchase;
        });

        StockService::flushCache();

        return response()->json(['message' => "Purchase {$purchase->reference_no} saved. Stock updated.", 'data' => new PurchaseResource($purchase)], 201);
    }

    public function show(Purchase $purchase)
    {
        return new PurchaseResource($purchase->load(['supplier', 'user:id,name', 'items.medicine:id,name,strength,unit']));
    }

    /** Void – only if nothing from this purchase was sold / returned / adjusted */
    public function destroy(Purchase $purchase)
    {
        $purchase->load('items.batch');
        foreach ($purchase->items as $item) {
            abort_if($item->batch && $item->batch->quantity !== $item->batch->initial_quantity, 422,
                'Some stock of this purchase is already sold / returned. Use "Return to supplier" instead.');
        }

        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                if ($item->batch) {
                    $this->stock->removeBatch($item->batch, $purchase->reference_no);
                }
            }
            Supplier::whereKey($purchase->supplier_id)->decrement('balance', (float) $purchase->due);
            Payment::where('purchase_id', $purchase->id)->delete();
            $purchase->delete();
        });

        StockService::flushCache();

        return response()->json(['message' => 'Purchase voided and stock removed.']);
    }
}
