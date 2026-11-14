<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\BranchContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /** Cached 2 min; cache cleared on every sale / stock change (StockService::flushCache) */
    public function __invoke()
    {
        $today = today();

        return response()->json(Cache::remember('dashboard.stats.' . BranchContext::id() . '.' . $today->toDateString(), 120, fn () => $this->build($today)));
    }

    private function build($today): array
    {
        $b = BranchContext::id();
        $days = (int) setting('expiry_alert_days', 60);
        $yesterday = $today->copy()->subDay();
        $monthStart = $today->copy()->startOfMonth();

        $profitExpr = DB::raw('(sale_items.price - sale_items.cost_price) * (sale_items.quantity - sale_items.returned_qty)');
        $profitBetween = fn ($from, $to) => (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.branch_id', $b)
            ->whereBetween('sales.sale_date', [$from, $to])->sum($profitExpr);
        $salesRow = fn ($d) => Sale::where('branch_id', $b)->where('sale_date', $d)->selectRaw('COUNT(*) c, COALESCE(SUM(total),0) t, COALESCE(SUM(returned_amount),0) r')->first();

        $t = $salesRow($today);
        $y = $salesRow($yesterday);
        $todayNet = (float) $t->t - (float) $t->r;
        $yesterdayNet = (float) $y->t - (float) $y->r;

        $expenseBetween = fn ($from, $to) => Schema::hasTable('expenses')
            ? (float) DB::table('expenses')->where('branch_id', $b)->where('status', 'approved')->whereBetween('expense_date', [$from, $to])->sum('amount') : 0.0;

        $monthSales = (float) Sale::where('branch_id', $b)->whereBetween('sale_date', [$monthStart, $today])->sum(DB::raw('total - returned_amount'));
        $monthProfit = $profitBetween($monthStart, $today);
        $monthExpenses = $expenseBetween($monthStart, $today);
        $todayProfit = $profitBetween($today, $today);
        $todayExpenses = $expenseBetween($today, $today);

        $batches = fn () => DB::table('medicine_batches')->where('branch_id', $b)->where('quantity', '>', 0);
        $expiringQ = fn () => $batches()->whereBetween('expiry_date', [$today, $today->copy()->addDays($days)]);
        $expiredQ = fn () => $batches()->where('expiry_date', '<', $today);

        $soldRecently = fn ($q) => $q->select('si.medicine_id')->from('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.branch_id', $b)->where('s.sale_date', '>=', $today->copy()->subDays(90))->whereNotNull('si.medicine_id');
        $dead = $batches()->whereNotIn('medicine_id', $soldRecently)->selectRaw('COUNT(DISTINCT medicine_id) c, COALESCE(SUM(quantity * purchase_price),0) v')->first();

        // ----- charts -----
        $series = function (int $n) use ($b, $today, $profitExpr) {
            $from = $today->copy()->subDays($n - 1);
            $sales = Sale::where('branch_id', $b)->where('sale_date', '>=', $from)->groupBy('sale_date')->selectRaw('sale_date, SUM(total - returned_amount) t')->pluck('t', 'sale_date');
            $profit = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.branch_id', $b)->where('sales.sale_date', '>=', $from)->groupBy('sales.sale_date')
                ->selectRaw('sales.sale_date as d, SUM((sale_items.price - sale_items.cost_price) * (sale_items.quantity - sale_items.returned_qty)) p')->pluck('p', 'd');
            $labels = $values = $profits = [];
            for ($i = $n - 1; $i >= 0; $i--) {
                $d = $today->copy()->subDays($i);
                $k = $d->toDateString();
                $labels[] = $d->format('d M');
                $values[] = round((float) ($sales[$k] ?? ($sales[$k . ' 00:00:00'] ?? 0)), 2);
                $profits[] = round((float) ($profit[$k] ?? ($profit[$k . ' 00:00:00'] ?? 0)), 2);
            }
            return ['labels' => $labels, 'values' => $values, 'profit' => $profits];
        };

        $hours = array_fill(0, 24, 0.0);
        Sale::where('branch_id', $b)->where('sale_date', $today)->get(['created_at', 'total'])->each(function ($s) use (&$hours) { $hours[(int) $s->created_at->format('G')] += (float) $s->total; });

        $payments = fn ($from, $to) => Sale::where('branch_id', $b)->whereBetween('sale_date', [$from, $to])->groupBy('payment_method')
            ->selectRaw('payment_method m, SUM(total - returned_amount) t')->pluck('t', 'm')->map(fn ($v) => round((float) $v, 2));

        $pending = [
            'prescriptions' => Schema::hasTable('prescriptions') ? (int) DB::table('prescriptions')->where('status', 'pending')->count() : 0,
            'purchase_orders' => Schema::hasTable('purchase_orders') ? (int) DB::table('purchase_orders')->where('branch_id', $b)->whereIn('status', ['pending_approval', 'approved'])->count() : 0,
            'return_requests' => Schema::hasTable('customer_return_requests') ? (int) DB::table('customer_return_requests')->where('branch_id', $b)->where('status', 'pending')->count() : 0,
            'online_orders' => Schema::hasTable('online_orders') ? (int) DB::table('online_orders')->where('branch_id', $b)->whereIn('status', ['pending', 'confirmed'])->count() : 0,
            'open_shifts' => (int) DB::table('cashier_sessions')->where('branch_id', $b)->whereNull('closed_at')->count(),
            'fbr_pending' => (int) DB::table('sales')->where('branch_id', $b)->where('fbr_status', 'pending')->count(),
        ];

        return [
            'cards' => [
                'today_sales'    => (float) $t->t,
                'today_returns'  => (float) SaleReturn::where('branch_id', $b)->where('return_date', $today)->sum('total'),
                'today_net'      => round($todayNet, 2),
                'today_invoices' => (int) $t->c,
                'avg_ticket'     => $t->c ? round((float) $t->t / (int) $t->c, 2) : 0,
                'yesterday_net'  => round($yesterdayNet, 2),
                'yesterday_invoices' => (int) $y->c,
                'change_pct'     => $yesterdayNet > 0 ? round(($todayNet - $yesterdayNet) / $yesterdayNet * 100, 1) : null,
                'today_profit'   => round($todayProfit, 2),
                'today_expenses' => $todayExpenses,
                'month_sales'    => round($monthSales, 2),
                'month_purchase' => (float) Purchase::where('branch_id', $b)->whereBetween('purchase_date', [$monthStart, $today])->sum('total'),
                'month_profit'   => round($monthProfit, 2),
                'month_expenses' => $monthExpenses,
                'month_net_profit' => round($monthProfit - $monthExpenses, 2),
                'margin_pct'     => $monthSales > 0 ? round($monthProfit / $monthSales * 100, 1) : 0,
                'medicines'      => Medicine::where('is_active', true)->count(),
                'low_stock'      => Medicine::where('is_active', true)->lowStock()->count(),
                'out_of_stock'   => Medicine::where('is_active', true)->whereRaw('NOT EXISTS (SELECT 1 FROM medicine_batches mb WHERE mb.medicine_id = medicines.id AND mb.branch_id = ? AND mb.quantity > 0)', [$b])->count(),
                'expiring'       => $expiringQ()->count(),
                'expiring_value' => round((float) $expiringQ()->sum(DB::raw('quantity * purchase_price')), 2),
                'expired'        => $expiredQ()->count(),
                'expired_value'  => round((float) $expiredQ()->sum(DB::raw('quantity * purchase_price')), 2),
                'dead_stock'     => (int) ($dead->c ?? 0),
                'dead_stock_value' => round((float) ($dead->v ?? 0), 2),
                'stock_value'    => round((float) $batches()->sum(DB::raw('quantity * purchase_price')), 2),
                'stock_sale_value' => round((float) $batches()->sum(DB::raw('quantity * sale_price')), 2),
                'customers'      => (int) DB::table('customers')->count(),
                'customer_due'   => (float) DB::table('customers')->sum('balance'),
                'supplier_due'   => (float) DB::table('suppliers')->sum('balance'),
            ],
            'pending' => $pending,
            'chart'   => $series(7),
            'chart30' => $series(30),
            'hourly'  => ['labels' => array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23)), 'values' => array_map(fn ($v) => round($v, 2), $hours)],
            'payments_today' => $payments($today, $today),
            'payments_month' => $payments($monthStart, $today),
            'top'     => SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.branch_id', $b)->where('sales.sale_date', '>=', $today->copy()->subDays(30))->groupBy('sale_items.medicine_name')
                ->selectRaw('sale_items.medicine_name as name, SUM(sale_items.quantity - sale_items.returned_qty) as qty')
                ->orderByDesc('qty')->limit(5)->get(),
            'top_categories' => DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')->join('medicines as m', 'm.id', '=', 'si.medicine_id')->leftJoin('categories as c', 'c.id', '=', 'm.category_id')
                ->where('s.branch_id', $b)->where('s.sale_date', '>=', $today->copy()->subDays(30))->groupBy('c.name')
                ->selectRaw("COALESCE(c.name, 'Uncategorised') as name, SUM(si.total) as total")->orderByDesc('total')->limit(6)->get(),
            'top_customers' => Sale::where('branch_id', $b)->whereNotNull('customer_id')->where('sale_date', '>=', $today->copy()->subDays(30))->groupBy('customer_id', 'customer_name')
                ->selectRaw('customer_id, customer_name as name, COUNT(*) as invoices, SUM(total - returned_amount) as total')->orderByDesc('total')->limit(5)->get(),
            'recent'  => Sale::where('branch_id', $b)->latest('id')->limit(7)->get(['id', 'invoice_no', 'customer_name', 'total', 'payment_status', 'created_at']),
            'recent_purchases' => Purchase::where('branch_id', $b)->with('supplier:id,name')->latest('id')->limit(5)->get(['id', 'reference_no', 'supplier_id', 'purchase_date', 'total', 'payment_status'])
                ->map(fn ($p) => ['id' => $p->id, 'invoice_no' => $p->reference_no, 'supplier' => $p->supplier?->name, 'date' => optional($p->purchase_date)->toDateString(), 'total' => (float) $p->total, 'payment_status' => $p->payment_status]),
            'expiring_list' => DB::table('medicine_batches as mb')->join('medicines as m', 'm.id', '=', 'mb.medicine_id')->where('mb.branch_id', $b)->where('mb.quantity', '>', 0)
                ->whereBetween('mb.expiry_date', [$today, $today->copy()->addDays($days)])->orderBy('mb.expiry_date')->limit(6)
                ->get(['m.id as medicine_id', 'm.name', 'm.strength', 'mb.batch_no', 'mb.quantity', 'mb.expiry_date'])
                ->map(function ($r) use ($today) { $r->days_left = $today->diffInDays(\Illuminate\Support\Carbon::parse($r->expiry_date), false); return $r; }),
            'low'     => Medicine::where('is_active', true)->withSellableStock()->withSum(['batches as branch_stock' => fn ($q) => $q->where('branch_id', $b)], 'quantity')->lowStock()->orderBy('branch_stock')->limit(7)
                ->get(['id', 'name', 'strength', 'total_stock', 'reorder_level', 'unit', 'branch_stock', 'sellable_stock'])->map(function ($m) { $m->total_stock = (int) $m->branch_stock; return $m; }),
        ];
    }
}
