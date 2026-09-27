<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $query = Sale::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('invoice_no', 'like', "%{$s}%")
                ->orWhere('customer_name', 'like', "%{$s}%")->orWhere('customer_phone', 'like', "{$s}%")))
            ->when($request->from, fn ($q, $d) => $q->where('sale_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('sale_date', '<=', $d))
            ->when($request->status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->method, fn ($q, $v) => $q->where('payment_method', $v));

        $totals = (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(due),0) as due, COALESCE(SUM(returned_amount),0) as returned')->first();

        return SaleResource::collection($query->with('user:id,name')->withCount('items')->latest('id')->paginate($request->integer('per_page', 15)))
            ->additional(['totals' => $totals]);
    }

    public function show(Sale $sale)
    {
        return new SaleResource($sale->load(['items', 'user:id,name', 'returns']));
    }

    /** Find sale by invoice number – used by Customer Return screen */
    public function findByInvoice(Request $request)
    {
        $request->validate(['invoice_no' => 'required|string|max:40']);
        $sale = Sale::with('items')->where('invoice_no', trim($request->invoice_no))->first();

        abort_unless($sale, 404, 'Invoice not found.');

        $days = (int) setting('return_days', 7);
        abort_if($days > 0 && $sale->sale_date->lt(today()->subDays($days)), 422, "Return period ({$days} days) is over for this invoice.");
        abort_if($sale->items->every(fn ($i) => $i->returned_qty >= $i->quantity), 422, 'All items of this invoice are already returned.');

        return new SaleResource($sale);
    }

    /** Void full sale (admin) – remaining stock back, due reversed */
    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                $qty = $item->quantity - $item->returned_qty;
                if ($qty > 0 && $item->medicine_id) {
                    $this->stock->restore($item->medicine_id, $item->medicine_batch_id, $qty, 'sale_void', $sale->invoice_no, 'Sale voided');
                }
            }
            if ($sale->due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', (float) $sale->due);
            }
            $sale->delete();
        });

        StockService::flushCache();

        return response()->json(['message' => "Sale {$sale->invoice_no} voided. Stock restored."]);
    }
}
