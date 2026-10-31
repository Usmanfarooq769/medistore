<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SendDailyStoreReport extends Command
{
    protected $signature = 'store:daily-report {--date= : Report date (YYYY-MM-DD)}';
    protected $description = 'Email the daily pharmacy operations report to the super admin';

    public function handle(): int
    {
        $date = $this->option('date') ?: today()->toDateString();
        $recipients = collect(preg_split('/[,;]+/', (string) setting('daily_report_email', '')))
            ->map(fn ($email) => trim($email))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($recipients->isEmpty()) {
            $recipients = User::where('role', 'admin')->whereNotNull('email')->pluck('email')
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
        }
        $recipients = $recipients->unique()->values();
        if ($recipients->isEmpty()) {
            $this->error('No valid daily report email is configured. Add it in Settings.');
            return self::FAILURE;
        }

        $sales = Sale::whereDate('sale_date', $date)->with('user:id,name')->withCount('items')->orderBy('id')->get();
        $purchases = Purchase::whereDate('purchase_date', $date)->with('supplier:id,name')->withCount('items')->orderBy('id')->get();
        $saleReturns = SaleReturn::whereDate('return_date', $date)->get();
        $purchaseReturns = PurchaseReturn::whereDate('return_date', $date)->get();
        $customerPayments = Payment::where('party_type', 'customer')->whereDate('payment_date', $date)->sum('amount');
        $supplierPayments = Payment::where('party_type', 'supplier')->whereDate('payment_date', $date)->sum('amount');
        $newCustomerRecords = Customer::whereDate('created_at', $date)->orderBy('id')->get(['name', 'phone', 'address']);
        $newMedicineRecords = Medicine::whereDate('created_at', $date)->orderBy('id')->get(['name', 'strength', 'barcode', 'pack_size', 'sale_price']);
        $newCustomers = $newCustomerRecords->count();
        $newMedicines = $newMedicineRecords->count();
        $adjustments = StockAdjustment::whereDate('created_at', $date)->count();
        $saleLines = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereDate('sales.sale_date', $date)->orderBy('sales.id')
            ->get(['sales.invoice_no', 'sales.customer_name', 'sale_items.medicine_name', 'sale_items.batch_no', 'sale_items.expiry_date', 'sale_items.quantity', 'sale_items.returned_qty', 'sale_items.price', 'sale_items.total']);
        $purchaseLines = DB::table('purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('medicines', 'medicines.id', '=', 'purchase_items.medicine_id')->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereDate('purchases.purchase_date', $date)->orderBy('purchases.id')
            ->get(['purchases.reference_no', 'suppliers.name as supplier_name', 'medicines.name as medicine_name', 'purchase_items.batch_no', 'purchase_items.expiry_date', 'purchase_items.quantity', 'purchase_items.bonus_qty', 'purchase_items.total']);
        $topMedicines = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereDate('sales.sale_date', $date)->groupBy('sale_items.medicine_name')
            ->selectRaw('sale_items.medicine_name, SUM(sale_items.quantity - sale_items.returned_qty) as quantity, SUM(sale_items.total) as sales_value')
            ->orderByDesc('quantity')->limit(10)->get();
        $lowStock = Medicine::where('is_active', true)->whereColumn('total_stock', '<=', 'reorder_level')->orderBy('total_stock')->limit(30)->get(['name', 'strength', 'total_stock', 'reorder_level']);
        $expiryDays = (int) setting('expiry_alert_days', 60);
        $expiring = DB::table('medicine_batches')->join('medicines', 'medicines.id', '=', 'medicine_batches.medicine_id')
            ->where('medicine_batches.quantity', '>', 0)->whereBetween('medicine_batches.expiry_date', [$date, date('Y-m-d', strtotime($date . " +{$expiryDays} days"))])
            ->orderBy('medicine_batches.expiry_date')->limit(30)
            ->get(['medicines.name', 'medicine_batches.batch_no', 'medicine_batches.expiry_date', 'medicine_batches.quantity']);
        $salesTotal = (float) $sales->sum('total');
        $salesCost = (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereDate('sales.sale_date', $date)
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * (sale_items.quantity - sale_items.returned_qty)),0) as cost')->value('cost');
        $soldRevenue = (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereDate('sales.sale_date', $date)
            ->selectRaw('COALESCE(SUM(sale_items.price * (sale_items.quantity - sale_items.returned_qty)),0) as revenue')->value('revenue');
        $profitEstimate = $soldRevenue - $salesCost - (float) $sales->sum('discount');

        $money = fn ($value) => e(setting('currency', 'Rs.') . ' ' . number_format((float) $value, 2));
        $rows = function ($collection, $columns) use ($money) {
            if ($collection->isEmpty()) return '<tr><td colspan="' . count($columns) . '">No records</td></tr>';
            return $collection->map(function ($item) use ($columns, $money) {
                return '<tr>' . collect($columns)->map(function ($column) use ($item, $money) {
                    $value = data_get($item, $column[0], '—');
                    if (($column[1] ?? '') === 'money') $value = $money($value);
                    return '<td>' . e((string) $value) . '</td>';
                })->implode('') . '</tr>';
            })->implode('');
        };
        $table = fn ($title, $headers, $body) => '<h3>' . e($title) . '</h3><table cellpadding="7" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;font-size:13px"><thead><tr>' . collect($headers)->map(fn ($h) => '<th align="left">' . e($h) . '</th>')->implode('') . '</tr></thead><tbody>' . $body . '</tbody></table><br>';

        $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:900px;margin:auto"><h2>' . e(setting('store_name', config('app.name'))) . ' — Daily Operations Report</h2><p>Date: <b>' . e($date) . '</b></p>';
        $metrics = [
            ['Sales invoices', $sales->count()], ['Gross sales', $money($sales->sum('subtotal'))], ['Discount', $money($sales->sum('discount'))],
            ['Net sales', $money($salesTotal)], ['Amount received on sales', $money($sales->sum('paid'))], ['Credit / outstanding on today sales', $money($sales->sum('due'))],
            ['Customer returns', $money($saleReturns->sum('total'))], ['Purchase bills', $purchases->count()], ['Purchase total', $money($purchases->sum('total'))],
            ['Supplier returns', $money($purchaseReturns->sum('total'))], ['Customer payments received', $money($customerPayments)], ['Supplier payments made', $money($supplierPayments)],
            ['Estimated gross profit', $money($profitEstimate)], ['Customers with sales today', $sales->pluck('customer_id')->filter()->unique()->count()], ['Walk-in invoices', $sales->whereNull('customer_id')->count()],
            ['New customers added', $newCustomers], ['New medicines added', $newMedicines], ['Stock adjustments', $adjustments],
        ];
        $html .= '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">' . collect($metrics)->map(fn ($m) => '<tr><th align="left">' . e($m[0]) . '</th><td>' . $m[1] . '</td></tr>')->implode('') . '</table><br>';
        $html .= $table('Sales invoices', ['Invoice', 'Customer', 'Total', 'Paid', 'Due', 'Cashier'], $rows($sales, [['invoice_no'], ['customer_name'], ['total','money'], ['paid','money'], ['due','money'], ['user.name']]));
        $html .= $table('Purchases', ['Reference', 'Supplier', 'Total', 'Paid', 'Due', 'Items'], $rows($purchases, [['reference_no'], ['supplier.name'], ['total','money'], ['paid','money'], ['due','money'], ['items_count']]));
        $html .= $table('Sold medicine line items', ['Invoice', 'Customer', 'Medicine', 'Batch', 'Expiry', 'Qty', 'Returned', 'Unit price', 'Line total'], $rows($saleLines, [['invoice_no'], ['customer_name'], ['medicine_name'], ['batch_no'], ['expiry_date'], ['quantity'], ['returned_qty'], ['price', 'money'], ['total', 'money']]));
        $html .= $table('New customers added today', ['Customer', 'Phone', 'Address'], $rows($newCustomerRecords, [['name'], ['phone'], ['address']]));
        $html .= $table('New medicines added today', ['Medicine', 'Strength', 'Barcode', 'Pack size', 'Sale price'], $rows($newMedicineRecords, [['name'], ['strength'], ['barcode'], ['pack_size'], ['sale_price', 'money']]));
        $html .= $table('Purchased medicine / stock received', ['Purchase', 'Supplier', 'Medicine', 'Batch', 'Expiry', 'Qty', 'Bonus', 'Line total'], $rows($purchaseLines, [['reference_no'], ['supplier_name'], ['medicine_name'], ['batch_no'], ['expiry_date'], ['quantity'], ['bonus_qty'], ['total','money']]));
        $html .= $table('Top selling medicines', ['Medicine', 'Quantity sold', 'Sales value'], $rows($topMedicines, [['medicine_name'], ['quantity'], ['sales_value','money']]));
        $html .= $table('Low stock medicines', ['Medicine', 'Stock', 'Reorder level'], $rows($lowStock, [['name'], ['total_stock'], ['reorder_level']]));
        $html .= $table("Batches expiring within {$expiryDays} days", ['Medicine', 'Batch', 'Expiry', 'Remaining'], $rows($expiring, [['name'], ['batch_no'], ['expiry_date'], ['quantity']]));
        $html .= '<p style="color:#777;font-size:11px">Automatically generated by ' . e(config('app.name')) . '. Profit is an estimate based on sold item cost and discounts; operational/accounting adjustments may affect final profit.</p></div>';

        foreach ($recipients as $recipient) {
            Mail::html($html, function ($message) use ($recipient, $date) {
                $message->to($recipient)->subject(setting('store_name', config('app.name')) . " — Daily Store Report ({$date})");
            });
        }
        $this->info('Daily report sent to ' . $recipients->implode(', '));
        return self::SUCCESS;
    }
}
