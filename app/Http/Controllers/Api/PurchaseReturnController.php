<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Http\Resources\PurchaseReturnResource;
use App\Models\MedicineBatch;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RETURN TO SUPPLIER – stock goes OUT of the chosen batch.
 * refund_type: adjust_balance => reduces what we owe the supplier
 *              cash           => supplier gives cash back
 */
class PurchaseReturnController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        return PurchaseReturnResource::collection(
            PurchaseReturn::with(['supplier:id,name', 'user:id,name'])->withCount('items')
                ->when($request->search, fn ($q, $s) => $q->where('return_no', 'like', "%{$s}%"))
                ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
                ->when($request->from, fn ($q, $d) => $q->where('return_date', '>=', $d))
                ->when($request->to, fn ($q, $d) => $q->where('return_date', '<=', $d))
                ->latest('id')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        return new PurchaseReturnResource($purchaseReturn->load(['supplier', 'user:id,name', 'items.medicine:id,name,strength']));
    }

    public function store(StorePurchaseReturnRequest $request)
    {
        $data = $request->validated();

        $return = DB::transaction(function () use ($data) {
            $return = PurchaseReturn::create([
                'supplier_id' => $data['supplier_id'],
                'return_date' => today(),
                'refund_type' => $data['refund_type'],
                'reason'      => $data['reason'] ?? null,
                'user_id'     => auth()->id(),
            ]);
            $return->update(['return_no' => 'PRT-' . now()->format('Ymd') . '-' . str_pad($return->id, 5, '0', STR_PAD_LEFT)]);

            $total = 0;
            foreach ($data['items'] as $row) {
                $batch = MedicineBatch::lockForUpdate()->findOrFail($row['medicine_batch_id']);
                $qty = (int) $row['quantity'];
                $lineTotal = round((float) $batch->purchase_price * $qty, 2);
                $total += $lineTotal;

                $this->stock->moveBatch($batch, -$qty, 'purchase_return', $return->return_no, $data['reason'] ?? 'Return to supplier');

                $return->items()->create([
                    'medicine_id'       => $batch->medicine_id,
                    'medicine_batch_id' => $batch->id,
                    'batch_no'          => $batch->batch_no,
                    'expiry_date'       => $batch->expiry_date,
                    'quantity'          => $qty,
                    'price'             => $batch->purchase_price,
                    'total'             => $lineTotal,
                ]);
            }

            $return->update(['total' => $total]);

            if ($data['refund_type'] === 'adjust_balance') {
                Supplier::whereKey($data['supplier_id'])->decrement('balance', $total);
            }

            return $return;
        });

        StockService::flushCache();

        return response()->json(['message' => "Return {$return->return_no} saved. Stock reduced.", 'data' => new PurchaseReturnResource($return)], 201);
    }
}
