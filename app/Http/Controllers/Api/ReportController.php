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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Daily closing – cash in hand */
    public function daily(Request $request)
    {
        $date = $request->date ? Carbon::parse($request->date) : today();

        $sales = Sale::where('sale_date', $date)->with('user:id,name')->withCount('items')->orderBy('id')->get();
        $byMethod = $sales->groupBy('payment_method')->map(fn ($g) => ['count' => $g->count(), 'total' => (float) $g->sum('paid')]);

        $returns = SaleReturn::where('return_date', $date)->get();
        $cashRefunds = (float) $returns->where('refund_method', 'cash')->sum('refund_amount');
        $supplierCashBack = (float) PurchaseReturn::where('return_date', $date)->where('refund_type', 'cash')->sum('total');

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
            'purchases'         => (float) Purchase::where('purchase_date', $date)->sum('total'),
            'supplier_paid'     => (float) Payment::where('party_type', 'supplier')->where('payment_date', $date)->where('method', 'cash')->sum('amount'),
            'customer_received' => (float) Payment::where('party_type', 'customer')->where('payment_date', $date)->where('method', 'cash')->sum('amount'),
            'supplier_cash_back'=> $supplierCashBack,
        ];
        $summary['cash_in_hand'] = (float) ($byMethod['cash']['total'] ?? 0) + $summary['customer_received'] + $supplierCashBack
            - $cashRefunds - $summary['supplier_paid'];

        $items = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.sale_date', $date)
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

        $sales = Sale::whereBetween('sale_date', [$from, $to])
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount),0) as discount,
                COALESCE(SUM(total),0) as net, COALESCE(SUM(returned_amount),0) as returned, COALESCE(SUM(due),0) as due')->first();

        $cogs = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * (sale_items.quantity - sale_items.returned_qty)),0) as cost,
                COALESCE(SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty)),0) as revenue')->first();

        $purchases = Purchase::whereBetween('purchase_date', [$from, $to])
            ->selectRaw('COUNT(*) as bills, COALESCE(SUM(total),0) as total, COALESCE(SUM(due),0) as due')->first();

        $adjLoss = (float) StockAdjustment::join('medicine_batches', 'medicine_batches.id', '=', 'stock_adjustments.medicine_batch_id')
            ->where('stock_adjustments.type', 'subtract')
            ->whereBetween(DB::raw('DATE(stock_adjustments.created_at)'), [$from->toDateString(), $to->toDateString()])
            ->sum(DB::raw('stock_adjustments.quantity * medicine_batches.purchase_price'));

        // customer returns that could NOT be restocked (damaged / expired) = loss
        $returnLoss = (float) SaleReturnItem::join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
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
                'customer' => (float) SaleReturn::whereBetween('return_date', [$from, $to])->sum('total'),
                'supplier' => (float) PurchaseReturn::whereBetween('return_date', [$from, $to])->sum('total'),
            ],
            'losses'       => ['adjustments' => $adjLoss, 'returns' => $returnLoss],
            'gross_profit' => $grossProfit,
            'net_profit'   => $grossProfit - $adjLoss - $returnLoss,
            'daily'        => Sale::whereBetween('sale_date', [$from, $to])->groupBy('sale_date')->orderBy('sale_date')
                ->selectRaw('sale_date, COUNT(*) as invoices, SUM(total - returned_amount) as total')->get(),
            'top'          => SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereBetween('sales.sale_date', [$from, $to])
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
        $totals = DB::table('medicine_batches')->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity * purchase_price),0) as cost_value, COALESCE(SUM(quantity * sale_price),0) as sale_value, COALESCE(SUM(quantity),0) as units')->first();

        $list = Medicine::with(['category:id,name', 'manufacturer:id,name'])
            ->addSelect(['medicines.*',
                'cost_value' => MedicineBatch::selectRaw('COALESCE(SUM(quantity * purchase_price),0)')->whereColumn('medicine_id', 'medicines.id')->where('quantity', '>', 0),
                'sale_value' => MedicineBatch::selectRaw('COALESCE(SUM(quantity * sale_price),0)')->whereColumn('medicine_id', 'medicines.id')->where('quantity', '>', 0),
            ])
            ->where('is_active', true)->search($request->search)->orderBy('name')->paginate(25);

        return MedicineResource::collection($list)->additional(['totals' => $totals]);
    }
}
