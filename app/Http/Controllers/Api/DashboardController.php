<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Cached 2 min; cache cleared on every sale / stock change */
    public function __invoke()
    {
        $today = today();

        return response()->json(Cache::remember('dashboard.stats.' . $today->toDateString(), 120, function () use ($today) {
            $days = (int) setting('expiry_alert_days', 60);
            $todaySales = Sale::where('sale_date', $today)->selectRaw('COUNT(*) c, COALESCE(SUM(total),0) t')->first();

            $chart = Sale::where('sale_date', '>=', $today->copy()->subDays(6))
                ->groupBy('sale_date')->selectRaw('sale_date, SUM(total - returned_amount) t')->pluck('t', 'sale_date');
            $labels = $values = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = $today->copy()->subDays($i);
                $labels[] = $d->format('d M');
                $values[] = round((float) ($chart[$d->toDateString()] ?? 0), 2);
            }

            return [
                'cards' => [
                    'today_sales'    => (float) $todaySales->t,
                    'today_invoices' => (int) $todaySales->c,
                    'today_returns'  => (float) SaleReturn::where('return_date', $today)->sum('total'),
                    'today_profit'   => (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.sale_date', $today)
                        ->sum(DB::raw('(sale_items.price - sale_items.cost_price) * (sale_items.quantity - sale_items.returned_qty)')),
                    'month_sales'    => (float) Sale::whereBetween('sale_date', [$today->copy()->startOfMonth(), $today])->sum(DB::raw('total - returned_amount')),
                    'month_purchase' => (float) Purchase::whereBetween('purchase_date', [$today->copy()->startOfMonth(), $today])->sum('total'),
                    'medicines'      => Medicine::where('is_active', true)->count(),
                    'low_stock'      => Medicine::where('is_active', true)->lowStock()->count(),
                    'expiring'       => DB::table('medicine_batches')->where('quantity', '>', 0)->whereBetween('expiry_date', [$today, $today->copy()->addDays($days)])->count(),
                    'expired'        => DB::table('medicine_batches')->where('quantity', '>', 0)->where('expiry_date', '<', $today)->count(),
                    'stock_value'    => (float) DB::table('medicine_batches')->where('quantity', '>', 0)->sum(DB::raw('quantity * purchase_price')),
                    'customer_due'   => (float) DB::table('customers')->sum('balance'),
                    'supplier_due'   => (float) DB::table('suppliers')->sum('balance'),
                ],
                'chart'  => ['labels' => $labels, 'values' => $values],
                'top'    => SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.sale_date', '>=', $today->copy()->subDays(30))->groupBy('sale_items.medicine_name')
                    ->selectRaw('sale_items.medicine_name as name, SUM(sale_items.quantity - sale_items.returned_qty) as qty')
                    ->orderByDesc('qty')->limit(5)->get(),
                'recent' => Sale::latest('id')->limit(7)->get(['id', 'invoice_no', 'customer_name', 'total', 'payment_status', 'created_at']),
                'low'    => Medicine::where('is_active', true)->lowStock()->orderBy('total_stock')->limit(7)
                    ->get(['id', 'name', 'strength', 'total_stock', 'reorder_level', 'unit']),
            ];
        }));
    }
}
