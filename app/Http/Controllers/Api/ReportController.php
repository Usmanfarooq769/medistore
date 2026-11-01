<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockAdjustment;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Daily closing – cash in hand */
    public function daily(Request $request)
    {
        $date = $request->date ? Carbon::parse($request->date) : today();

        $sales = Sale::where('branch_id', BranchContext::id())->where('sale_date', $date)->with('user:id,name')->withCount('items')->orderBy('id')->get();
        $byMethod = $sales->groupBy('payment_method')->map(fn ($g) => ['count' => $g->count(), 'total' => (float) $g->sum('paid')]);

        $returns = SaleReturn::where('branch_id', BranchContext::id())->where('return_date', $date)->get();
        $cashRefunds = (float) $returns->where('refund_method', 'cash')->sum('refund_amount');
        $supplierCashBack = (float) PurchaseReturn::where('branch_id', BranchContext::id())->where('return_date', $date)->where('refund_type', 'cash')->sum('total');

        $summary = [
            'invoices'          => $sales->count(),
            'gross'             => (float) $sales->sum('subtotal'),
            'discount'          => (float) $sales->sum('discount'),
            'tax'               => (float) $sales->sum('tax'),
            'net'               => (float) $sales->sum('total'),
            'credit'            => (float) $sales->sum('due'),
            'returns'           => (float) $returns->sum('total'),
            'returns_count'     => $returns->count(),
            'cash_refunds'      => $cashRefunds,
            'purchases'         => (float) Purchase::where('branch_id', BranchContext::id())->where('purchase_date', $date)->sum('total'),
            'supplier_paid'     => (float) Payment::where('branch_id', BranchContext::id())->where('party_type', 'supplier')->where('payment_date', $date)->where('method', 'cash')->sum('amount'),
            'customer_received' => (float) Payment::where('branch_id', BranchContext::id())->where('party_type', 'customer')->where('payment_date', $date)->where('method', 'cash')->sum('amount'),
            'supplier_cash_back'=> $supplierCashBack,
        ];
        $summary['cash_in_hand'] = (float) ($byMethod['cash']['total'] ?? 0) + $summary['customer_received'] + $supplierCashBack
            - $cashRefunds - $summary['supplier_paid'];

        $items = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.branch_id', BranchContext::id())->where('sales.sale_date', $date)
            ->groupBy('sale_items.medicine_name')
            ->selectRaw('sale_items.medicine_name, SUM(sale_items.quantity - sale_items.returned_qty) as qty,
                SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty)) as revenue,
                SUM(sale_items.cost_price * (sale_items.quantity - sale_items.returned_qty)) as cost')
            ->orderByDesc('qty')->get();
        $summary['profit'] = (float) $items->sum('revenue') - (float) $items->sum('cost') - $summary['discount'];

        return response()->json([
            'date'      => $date->toDateString(),
            'summary'   => $summary,
            'by_method' => $byMethod,
            'items'     => $items,
            'sales'     => $sales->map(fn ($s) => [
                'id' => $s->id, 'invoice_no' => $s->invoice_no, 'time' => $s->created_at->format('h:i A'), 'customer_name' => $s->customer_name,
                'items_count' => $s->items_count, 'total' => (float) $s->total, 'paid' => (float) $s->paid, 'due' => (float) $s->due,
                'payment_method' => $s->payment_method, 'user' => $s->user?->name,
            ]),
            'returns'   => $returns->map(fn ($r) => ['id' => $r->id, 'return_no' => $r->return_no, 'total' => (float) $r->total, 'refund_method' => $r->refund_method]),
        ]);
    }

    /** Sales, returns, purchases & profit for a period */
    public function summary(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : today()->startOfMonth();
        $to   = $request->to ? Carbon::parse($request->to) : today();

        $sales = Sale::where('branch_id', BranchContext::id())->whereBetween('sale_date', [$from, $to])
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount),0) as discount,
                COALESCE(SUM(total),0) as net, COALESCE(SUM(returned_amount),0) as returned, COALESCE(SUM(due),0) as due')->first();

        $cogs = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.branch_id', BranchContext::id())->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * (sale_items.quantity - sale_items.returned_qty)),0) as cost,
                COALESCE(SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty)),0) as revenue')->first();

        $purchases = Purchase::where('branch_id', BranchContext::id())->whereBetween('purchase_date', [$from, $to])
            ->selectRaw('COUNT(*) as bills, COALESCE(SUM(total),0) as total, COALESCE(SUM(due),0) as due')->first();

        $adjLoss = (float) StockAdjustment::join('medicine_batches', 'medicine_batches.id', '=', 'stock_adjustments.medicine_batch_id')->where('stock_adjustments.branch_id', BranchContext::id())
            ->where('stock_adjustments.type', 'subtract')
            ->whereBetween(DB::raw('DATE(stock_adjustments.created_at)'), [$from->toDateString(), $to->toDateString()])
            ->sum(DB::raw('stock_adjustments.quantity * medicine_batches.purchase_price'));

        // customer returns that could NOT be restocked (damaged / expired) = loss
        $returnLoss = (float) SaleReturnItem::join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')->where('sale_returns.branch_id', BranchContext::id())
            ->whereBetween('sale_returns.return_date', [$from, $to])->where('sale_return_items.restocked', false)
            ->sum('sale_return_items.total');

        $grossProfit = (float) $cogs->revenue - (float) $cogs->cost - (float) $sales->discount;

        return response()->json([
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'sales'        => $sales,
            'cogs'         => $cogs,
            'purchases'    => $purchases,
            'returns'      => [
                'customer' => (float) SaleReturn::where('branch_id', BranchContext::id())->whereBetween('return_date', [$from, $to])->sum('total'),
                'supplier' => (float) PurchaseReturn::where('branch_id', BranchContext::id())->whereBetween('return_date', [$from, $to])->sum('total'),
            ],
            'losses'       => ['adjustments' => $adjLoss, 'returns' => $returnLoss],
            'gross_profit' => $grossProfit,
            'net_profit'   => $grossProfit - $adjLoss - $returnLoss,
            'daily'        => Sale::where('branch_id', BranchContext::id())->whereBetween('sale_date', [$from, $to])->groupBy('sale_date')->orderBy('sale_date')
                ->selectRaw('sale_date, COUNT(*) as invoices, SUM(total - returned_amount) as total')->get(),
            'top'          => SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.branch_id', BranchContext::id())->whereBetween('sales.sale_date', [$from, $to])
                ->groupBy('sale_items.medicine_name')
                ->selectRaw('sale_items.medicine_name, SUM(sale_items.quantity - sale_items.returned_qty) as qty,
                    SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty)) as revenue,
                    SUM((sale_items.price - sale_items.cost_price) * (sale_items.quantity - sale_items.returned_qty)) as profit')
                ->orderByDesc('qty')->limit(15)->get(),
        ]);
    }

    /** Current stock valuation */
    public function stock(Request $request)
    {
        $totals = DB::table('medicine_batches')->where('branch_id', BranchContext::id())->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity * purchase_price),0) as cost_value, COALESCE(SUM(quantity * sale_price),0) as sale_value, COALESCE(SUM(quantity),0) as units')->first();

        $list = Medicine::with(['category:id,name', 'manufacturer:id,name'])->withSellableStock()->withSum(['batches as branch_stock' => fn($b) => $b->where('branch_id', BranchContext::id())], 'quantity')
            ->addSelect(['medicines.*',
                'cost_value' => MedicineBatch::selectRaw('COALESCE(SUM(quantity * purchase_price),0)')->whereColumn('medicine_id', 'medicines.id')->where('branch_id', BranchContext::id())->where('quantity', '>', 0),
                'sale_value' => MedicineBatch::selectRaw('COALESCE(SUM(quantity * sale_price),0)')->whereColumn('medicine_id', 'medicines.id')->where('branch_id', BranchContext::id())->where('quantity', '>', 0),
            ])
            ->where('is_active', true)->search($request->search)->orderBy('name')->paginate(25);

        return MedicineResource::collection($list)->additional(['totals' => $totals]);
    }

    /** Batch-level profitability using immutable sale-item cost snapshots. */
    public function batchProfitability(Request $request)
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();
        $rows = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('medicine_batches', 'medicine_batches.id', '=', 'sale_items.medicine_batch_id')
            ->where('sales.branch_id', BranchContext::id())->whereBetween('sales.sale_date', [$from, $to])
            ->groupBy('sale_items.medicine_id', 'sale_items.medicine_name', 'sale_items.medicine_batch_id', 'sale_items.batch_no', 'sale_items.expiry_date')
            ->selectRaw('sale_items.medicine_id, sale_items.medicine_name, sale_items.medicine_batch_id, sale_items.batch_no, sale_items.expiry_date,
                SUM(sale_items.quantity - sale_items.returned_qty) as sold_base_units,
                SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty) * (1 - COALESCE(sales.discount / NULLIF(sales.subtotal, 0), 0))) as revenue,
                SUM(sale_items.cost_price * (sale_items.quantity - sale_items.returned_qty)) as cost,
                SUM((sale_items.price * (1 - COALESCE(sales.discount / NULLIF(sales.subtotal, 0), 0)) - sale_items.cost_price) * (sale_items.quantity - sale_items.returned_qty)) as gross_profit')
            ->orderByDesc('gross_profit')->get();
        return response()->json(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'totals' => [
            'revenue' => (float) $rows->sum('revenue'), 'cost' => (float) $rows->sum('cost'), 'gross_profit' => (float) $rows->sum('gross_profit'), 'units' => (int) $rows->sum('sold_base_units'),
        ], 'data' => $rows]);
    }

    /** Demand-based replenishment suggestions for the active branch. */
    public function reorderSuggestions(Request $request)
    {
        $days = max(7, min(90, $request->integer('history_days', 30)));
        $lead = max(1, min(60, $request->integer('lead_days', 7)));
        $target = max($lead, min(120, $request->integer('target_days', 30)));
        $branchId = BranchContext::id();
        $sold = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.sale_date', '>=', today()->subDays($days))
            ->whereNotNull('sale_items.medicine_id')->groupBy('sale_items.medicine_id')
            ->selectRaw('sale_items.medicine_id, SUM(sale_items.quantity - sale_items.returned_qty) as units_sold')
            ->pluck('units_sold', 'medicine_id');
        $stock = MedicineBatch::where('branch_id', $branchId)->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()))
            ->groupBy('medicine_id')->selectRaw('medicine_id, SUM(quantity) as stock_units')->pluck('stock_units', 'medicine_id');
        $incoming = DB::table('purchase_order_items')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('medicines', 'medicines.id', '=', 'purchase_order_items.medicine_id')
            ->where('purchase_orders.branch_id', $branchId)->whereIn('purchase_orders.status', ['pending_approval', 'approved'])
            ->groupBy('purchase_order_items.medicine_id')
            ->selectRaw('purchase_order_items.medicine_id, SUM((purchase_order_items.quantity + purchase_order_items.bonus_qty) * COALESCE(medicines.pack_size,1)) as incoming_units')
            ->pluck('incoming_units', 'medicine_id');
        $items = Medicine::where('is_active', true)->orderBy('name')->get(['id','name','strength','unit','reorder_level']);
        $rows = $items->map(function ($medicine) use ($sold, $stock, $incoming, $days, $lead, $target) {
            $unitsSold = (float) ($sold[$medicine->id] ?? 0);
            $current = (int) ($stock[$medicine->id] ?? 0);
            $incomingUnits = (int) ($incoming[$medicine->id] ?? 0);
            $effectiveStock = $current + $incomingUnits;
            $daily = $unitsSold / $days;
            $daysCover = $daily > 0 ? round($effectiveStock / $daily, 1) : null;
            $reorderPoint = (int) ceil($daily * $lead) + (int) $medicine->reorder_level;
            $targetStock = (int) ceil($daily * $target) + (int) $medicine->reorder_level;
            $suggested = $effectiveStock <= $reorderPoint ? max(0, $targetStock - $effectiveStock) : 0;
            return ['medicine_id'=>$medicine->id,'name'=>$medicine->name,'strength'=>$medicine->strength,'unit'=>$medicine->unit,
                'current_stock'=>$current,'incoming_units'=>$incomingUnits,'effective_stock'=>$effectiveStock,'units_sold'=>$unitsSold,'average_daily_sales'=>round($daily,2),'days_of_stock'=>$daysCover,
                'reorder_level'=>(int)$medicine->reorder_level,'reorder_point'=>$reorderPoint,'suggested_quantity'=>$suggested];
        })->filter(fn ($row) => $row['suggested_quantity'] > 0)->sortBy('days_of_stock')->values();

        return response()->json(['parameters'=>['history_days'=>$days,'lead_days'=>$lead,'target_days'=>$target], 'data'=>$rows]);
    }

    /** Outstanding customer invoices grouped into ageing buckets for the active branch. */
    public function customerAging()
    {
        $rows = Sale::with('customer:id,name,phone')->where('branch_id', BranchContext::id())->where('due', '>', 0)->get(['id','customer_id','customer_name','invoice_no','sale_date','due']);
        $customers = $rows->groupBy('customer_id')->map(function ($invoices, $customerId) {
            $buckets = ['current'=>0,'days_1_30'=>0,'days_31_60'=>0,'days_61_90'=>0,'over_90'=>0];
            foreach ($invoices as $invoice) {
                $age = $invoice->sale_date->diffInDays(today());
                $key = $age <= 0 ? 'current' : ($age <= 30 ? 'days_1_30' : ($age <= 60 ? 'days_31_60' : ($age <= 90 ? 'days_61_90' : 'over_90')));
                $buckets[$key] += (float) $invoice->due;
            }
            return ['customer_id'=>$customerId,'customer_name'=>$invoices->first()->customer?->name ?? $invoices->first()->customer_name,
                'phone'=>$invoices->first()->customer?->phone,'total_due'=>round((float)$invoices->sum('due'),2),
                'invoice_count'=>$invoices->count(),'buckets'=>array_map(fn($v)=>round($v,2),$buckets)];
        })->values()->sortByDesc('total_due');
        return response()->json(['data'=>$customers]);
    }

}
