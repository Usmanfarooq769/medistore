<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ManufacturerScheme;
use App\Models\Supplier;
use App\Services\StockService;
use App\Services\BranchContext;
use App\Services\JournalPostingService;
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
            Purchase::with('supplier:id,name')->withCount('items')->where('branch_id', BranchContext::id())
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
                'branch_id'           => BranchContext::id(),
            ]);
            $purchase->update(['reference_no' => 'PUR-' . now()->format('Ymd') . '-' . str_pad($purchase->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($data['items'] as $row) {
                $medicine = Medicine::lockForUpdate()->findOrFail($row['medicine_id']);
                if (! $medicine->stock_units_are_base && (int) $medicine->pack_size > 1 && ((int) $medicine->total_stock > 0 || $medicine->movements()->exists())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Convert legacy stock to individual units for {$medicine->display_name} before recording another purchase."]);
                }
                if (! $medicine->stock_units_are_base) $medicine->forceFill(['stock_units_are_base' => true])->save();
                $packSize = max(1, (int) ($medicine->pack_size ?? 1));
                $bonusQty = (int) ($row['bonus_qty'] ?? 0);
                $scheme = null;
                if (!empty($row['scheme_id'])) {
                    $scheme = ManufacturerScheme::whereKey($row['scheme_id'])->lockForUpdate()->firstOrFail();
                    if (!$scheme->is_active || !$scheme->valid_from->lte(today()) || !$scheme->valid_to->gte(today())) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Scheme {$scheme->name} is not active today."]);
                    }
                    if ($scheme->scheme_type !== 'buy_x_get_y') {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Only Buy X Get Y schemes can be applied directly to purchase stock.']);
                    }
                    if ($scheme->medicine_id && (int)$scheme->medicine_id !== (int)$medicine->id) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Scheme {$scheme->name} does not apply to {$medicine->name}."]);
                    }
                    if ($scheme->supplier_id && (int)$scheme->supplier_id !== (int)$data['supplier_id']) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Scheme {$scheme->name} is restricted to another supplier."]);
                    }
                    if ($scheme->manufacturer_id && (int)$scheme->manufacturer_id !== (int)$medicine->manufacturer_id) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Scheme {$scheme->name} does not match this medicine manufacturer."]);
                    }
                    if ($scheme->branch_id && (int)$scheme->branch_id !== BranchContext::id()) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Scheme {$scheme->name} is not available for this branch."]);
                    }
                    $schemeFree = intdiv((int)$row['quantity'], max(1,(int)$scheme->buy_quantity)) * (int)$scheme->free_quantity;
                    if ($scheme->max_free_quantity) $schemeFree = min($schemeFree,(int)$scheme->max_free_quantity);
                    $bonusQty += $schemeFree;
                }
                $qty = ((int) $row['quantity'] + $bonusQty) * $packSize;

                $batch = $this->stock->addBatch($medicine, [
                    'purchase_id'    => $purchase->id,
                    'branch_id'      => BranchContext::id(),
                    'supplier_id'    => $data['supplier_id'],
                    'batch_no'       => $row['batch_no'],
                    'expiry_date'    => $row['expiry_date'],
                    'purchase_price' => round(((float) $row['purchase_price'] * ((int) $row['quantity'])) / max(1, ((int) $row['quantity'] + $bonusQty)) / $packSize, 4),
                    'sale_price'     => round((float) $row['sale_price'] / $packSize, 4),
                    'quantity'       => $qty,
                ], $purchase->reference_no);

                $purchase->items()->create([
                    'medicine_id'       => $medicine->id,
                    'medicine_batch_id' => $batch->id,
                    'batch_no'          => $row['batch_no'],
                    'expiry_date'       => $row['expiry_date'],
                    'quantity'          => $row['quantity'],
                    'bonus_qty'         => $bonusQty,
                    'purchase_price'    => $row['purchase_price'],
                    'sale_price'        => $row['sale_price'],
                    'total'             => round($row['quantity'] * $row['purchase_price'], 2),
                    'scheme_id'         => $scheme?->id,
                ]);

                if ($scheme && $bonusQty > (int)($row['bonus_qty'] ?? 0)) {
                    $schemeFree = $bonusQty - (int)($row['bonus_qty'] ?? 0);
                    DB::table('manufacturer_scheme_redemptions')->insert([
                        'scheme_id' => $scheme->id, 'purchase_id' => $purchase->id, 'medicine_id' => $medicine->id,
                        'branch_id' => BranchContext::id(), 'paid_quantity' => (int)$row['quantity'], 'free_quantity' => $schemeFree,
                        'purchase_value' => round((int)$row['quantity'] * (float)$row['purchase_price'], 2),
                        'estimated_saving' => round($schemeFree * (float)$row['purchase_price'], 2),
                        'redeemed_at' => $data['purchase_date'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                $medicine->forceFill(['purchase_price' => $row['purchase_price'], 'sale_price' => $row['sale_price']])->saveQuietly();
            }

            if ($paid > 0) {
                Payment::create([
                    'party_type' => 'supplier', 'party_id' => $data['supplier_id'], 'purchase_id' => $purchase->id,
                    'amount' => $paid, 'method' => $data['payment_method'] ?? 'cash', 'payment_date' => $data['purchase_date'],
                    'note' => 'Paid with ' . $purchase->reference_no, 'user_id' => auth()->id(), 'branch_id' => BranchContext::id(),
                ]);
            }

            Supplier::whereKey($data['supplier_id'])->increment('balance', $total - $paid);
            app(JournalPostingService::class)->postPurchase($purchase->load('items'));

            return $purchase;
        });

        StockService::flushCache();

        return response()->json(['message' => "Purchase {$purchase->reference_no} saved. Stock updated.", 'data' => new PurchaseResource($purchase)], 201);
    }

    public function show(Purchase $purchase)
    {
        abort_unless((int)$purchase->branch_id === BranchContext::id(), 404);
        return new PurchaseResource($purchase->load(['supplier', 'user:id,name', 'items.medicine:id,name,strength,unit']));
    }

    /** Void – only if nothing from this purchase was sold / returned / adjusted */
    public function destroy(Purchase $purchase)
    {
        abort_unless((int)$purchase->branch_id === BranchContext::id(), 404);
        abort_if((float)$purchase->paid > 0, 422, 'This purchase has supplier payments. Process a purchase return or payment adjustment instead of voiding it.');
        $purchase->load('items.batch');
        foreach ($purchase->items as $item) {
            abort_if($item->batch && $item->batch->quantity !== $item->batch->initial_quantity, 422,
                'Some stock of this purchase is already sold / returned. Use "Return to supplier" instead.');
        }

        DB::transaction(function () use ($purchase) {
            app(JournalPostingService::class)->reverseSource(Purchase::class, $purchase->id, (int) $purchase->branch_id, 'Purchase voided ' . $purchase->reference_no);
            foreach ($purchase->items as $item) {
                if ($item->batch) {
                    $this->stock->removeBatch($item->batch, $purchase->reference_no);
                }
            }
            Supplier::whereKey($purchase->supplier_id)->decrement('balance', (float) $purchase->due);
            Payment::where('purchase_id', $purchase->id)->delete();
            if (\Illuminate\Support\Facades\Schema::hasTable('manufacturer_scheme_redemptions')) DB::table('manufacturer_scheme_redemptions')->where('purchase_id',$purchase->id)->delete();
            $purchase->delete();
        });

        StockService::flushCache();

        return response()->json(['message' => 'Purchase voided and stock removed.']);
    }
}
