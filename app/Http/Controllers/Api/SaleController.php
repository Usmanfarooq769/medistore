<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Promotion;
use App\Models\Sale;
use App\Services\StockService;
use App\Services\BranchContext;
use App\Services\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $query = Sale::query()->where('branch_id', BranchContext::id())
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
        abort_unless((int)$sale->branch_id === BranchContext::id(), 404);
        return new SaleResource($sale->load(['items', 'user:id,name', 'returns']));
    }

    /** Find sale by invoice number – used by Customer Return screen */
    public function findByInvoice(Request $request)
    {
        $request->validate(['invoice_no' => 'required|string|max:40']);
        $sale = Sale::with('items')->where('branch_id', BranchContext::id())->where('invoice_no', trim($request->invoice_no))->first();

        abort_unless($sale, 404, 'Invoice not found.');

        $days = (int) setting('return_days', 7);
        abort_if($days > 0 && $sale->sale_date->lt(today()->subDays($days)), 422, "Return period ({$days} days) is over for this invoice.");
        abort_if($sale->items->every(fn ($i) => $i->returned_qty >= $i->quantity), 422, 'All items of this invoice are already returned.');

        return new SaleResource($sale);
    }

    /** Void full sale (admin) – remaining stock back, due reversed */
    public function destroy(Request $request, Sale $sale)
    {
        abort_unless((int)$sale->branch_id === BranchContext::id(), 404);
        $approver = $request->user();
        if (! $approver->hasPermission('sales.void')) {
            $approver = app(\App\Services\ManagerPinService::class)->approver($request->input('manager_pin'), 'sales.void', $request->user());
            if (! $approver) return response()->json(['message' => 'Voiding an invoice needs a manager PIN.', 'requires_manager_pin' => true], 403);
        }
        \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'action' => 'sale_voided', 'subject_type' => Sale::class, 'subject_id' => $sale->id,
            'new_values' => ['invoice_no' => $sale->invoice_no, 'approved_by' => $approver->id, 'reason' => $request->input('reason')], 'ip_address' => $request->ip()]);
        abort_if((float)$sale->paid > 0 || (float)$sale->returned_amount > 0, 422, 'This invoice has received payments or returns. Process a return/refund instead of voiding it.');
        DB::transaction(function () use ($sale) {
            app(JournalPostingService::class)->reverseSource(Sale::class, $sale->id, (int) $sale->branch_id, 'Sale voided ' . $sale->invoice_no);
            foreach ($sale->items as $item) {
                $qty = $item->quantity - $item->returned_qty;
                if ($qty > 0 && $item->medicine_id) {
                    $this->stock->restore($item->medicine_id, $item->medicine_batch_id, $qty, 'sale_void', $sale->invoice_no, 'Sale voided');
                }
            }
            if ($sale->due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', (float) $sale->due);
            }
            if ($sale->customer_id && (int) $sale->loyalty_points_redeemed > 0) {
                $customer = Customer::lockForUpdate()->find($sale->customer_id);
                $customer->increment('loyalty_points', (int) $sale->loyalty_points_redeemed);
                LoyaltyTransaction::create(['customer_id'=>$customer->id,'sale_id'=>$sale->id,'user_id'=>auth()->id(),'type'=>'restored','points'=>(int)$sale->loyalty_points_redeemed,'balance_after'=>(int)$customer->fresh()->loyalty_points,'note'=>'Points restored after voiding invoice '.$sale->invoice_no]);
            }
            if ($sale->promotion_id) Promotion::whereKey($sale->promotion_id)->where('used_count','>',0)->decrement('used_count');
            $sale->delete();
        });

        StockService::flushCache();

        return response()->json(['message' => "Sale {$sale->invoice_no} voided. Stock restored."]);
    }
}
