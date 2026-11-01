<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CashierSessionController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ManufacturerController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/* All routes are prefixed with /api (bootstrap/app.php) */

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/* ---------------- Admin + Cashier ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,cashier'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('lookups', LookupController::class);

    // POS
    Route::get('pos/products', [PosController::class, 'products']);
    Route::get('pos/scan/{barcode}', [PosController::class, 'scan'])->where('barcode', '.*');
    Route::post('pos/checkout', [PosController::class, 'checkout']);

    // Cashier shift and till reconciliation
    Route::get('cashier-session/current', [CashierSessionController::class, 'current']);
    Route::post('cashier-session/open', [CashierSessionController::class, 'open']);
    Route::post('cashier-session/transaction', [CashierSessionController::class, 'transaction']);
    Route::post('cashier-session/close', [CashierSessionController::class, 'close']);

    // Medicines lookup (POS / returns)
    Route::get('medicines/search', [MedicineController::class, 'search']);
    Route::get('medicines/{medicine}/batches', [MedicineController::class, 'batches']);

    // Prescriptions: cashiers can upload/view; only admin can approve/reject.
    Route::get('tax-profiles', [\App\Http\Controllers\Api\TaxProfileController::class, 'index']);
    Route::get('prescriptions', [PrescriptionController::class, 'index']);
    Route::get('prescriptions/find/{prescriptionNo}', [PrescriptionController::class, 'find']);
    Route::get('prescriptions/{prescription}/file', [PrescriptionController::class, 'file']);
    Route::post('prescriptions', [PrescriptionController::class, 'store']);

    // Customers
    Route::get('customers/{customer}/sales', [CustomerController::class, 'sales']);
    Route::post('customers/{customer}/payments', [CustomerController::class, 'payment']);
    Route::apiResource('customers', CustomerController::class)->except('destroy');

    // Sales
    Route::get('sales/find', [SaleController::class, 'findByInvoice']);
    Route::get('sales', [SaleController::class, 'index']);
    Route::get('sales/{sale}', [SaleController::class, 'show']);

    // CUSTOMER RETURNS (stock back in)
    Route::get('sale-returns', [SaleReturnController::class, 'index']);
    Route::post('sale-returns', [SaleReturnController::class, 'store']);
    Route::get('sale-returns/{saleReturn}', [SaleReturnController::class, 'show']);

    // Alerts & daily closing
    Route::get('alerts/counts', [AlertController::class, 'counts']);
    Route::get('alerts/low-stock', [AlertController::class, 'lowStock']);
    Route::get('alerts/expiry', [AlertController::class, 'expiry']);
    Route::get('reports/daily', [ReportController::class, 'daily']);
});

/* ---------------- Admin only ---------------- */
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::post('prescriptions/{prescription}/review', [PrescriptionController::class, 'review']);

    // Purchase orders and receiving workflow
    Route::get('purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'store']);
    Route::get('purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'show']);
    Route::post('purchase-orders/{purchaseOrder}/review', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'receive']);

    // Branches and inter-branch transfers
    Route::get('branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
    Route::post('branches', [\App\Http\Controllers\Api\BranchController::class, 'store']);
    Route::put('branches/{branch}', [\App\Http\Controllers\Api\BranchController::class, 'update']);
    Route::get('branch-transfers', [\App\Http\Controllers\Api\BranchTransferController::class, 'index']);
    Route::post('branch-transfers', [\App\Http\Controllers\Api\BranchTransferController::class, 'store']);
    Route::post('branch-transfers/{transfer}/review', [\App\Http\Controllers\Api\BranchTransferController::class, 'approve']);
    Route::get('branch-stock', [\App\Http\Controllers\Api\BranchTransferController::class, 'stock']);

    // Expenses, tax and accounting
    Route::apiResource('expenses', \App\Http\Controllers\Api\ExpenseController::class)->except('show');
    Route::post('tax-profiles', [\App\Http\Controllers\Api\TaxProfileController::class, 'store']);
    Route::put('tax-profiles/{taxProfile}', [\App\Http\Controllers\Api\TaxProfileController::class, 'update']);
    Route::delete('tax-profiles/{taxProfile}', [\App\Http\Controllers\Api\TaxProfileController::class, 'destroy']);
    Route::get('reports/tax', [\App\Http\Controllers\Api\TaxProfileController::class, 'report']);
    Route::get('accounting/accounts', [\App\Http\Controllers\Api\AccountingController::class, 'accounts']);
    Route::get('accounting/journals', [\App\Http\Controllers\Api\AccountingController::class, 'journals']);
    Route::post('accounting/journals', [\App\Http\Controllers\Api\AccountingController::class, 'storeJournal']);
    Route::get('accounting/income-statement', [\App\Http\Controllers\Api\AccountingController::class, 'incomeStatement']);
    Route::get('accounting/cash-flow', [\App\Http\Controllers\Api\AccountingController::class, 'cashFlow']);
    Route::get('accounting/balance-sheet', [\App\Http\Controllers\Api\AccountingController::class, 'balanceSheet']);
    Route::get('accounting/trial-balance', [\App\Http\Controllers\Api\AccountingController::class, 'trialBalance']);
    Route::get('backups', fn () => \App\Models\BackupRun::latest()->paginate(20));

    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::apiResource('manufacturers', ManufacturerController::class)->except('show');

    Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger']);
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'payment']);
    Route::apiResource('suppliers', SupplierController::class);

    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('medicines/{medicine}/movements', [MedicineController::class, 'movements']);
    Route::post('medicines/{medicine}/convert-stock-units', [MedicineController::class, 'convertStockUnits']);
    Route::get('medicines/import-template', [MedicineController::class, 'downloadImportTemplate']);
    Route::post('medicines/import-csv', [MedicineController::class, 'importCsv']);
    Route::apiResource('medicines', MedicineController::class);

    Route::apiResource('purchases', PurchaseController::class)->except('update');

    Route::delete('sales/{sale}', [SaleController::class, 'destroy']);

    // RETURN TO SUPPLIER (stock out)
    Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store']);
    Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show']);

    Route::get('adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('adjustments', [StockAdjustmentController::class, 'store']);
    Route::post('adjustments/write-off-expired', [StockAdjustmentController::class, 'writeOffExpired']);

    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('reports/stock', [ReportController::class, 'stock']);
    Route::get('reports/batch-profitability', [ReportController::class, 'batchProfitability']);
    Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'update']);

    Route::apiResource('users', UserController::class)->except('show');
});
