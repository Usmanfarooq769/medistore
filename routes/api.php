<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
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
    Route::post('pos/checkout', [PosController::class, 'checkout']);

    // Medicines lookup (POS / returns)
    Route::get('medicines/search', [MedicineController::class, 'search']);
    Route::get('medicines/{medicine}/batches', [MedicineController::class, 'batches']);

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

    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::apiResource('manufacturers', ManufacturerController::class)->except('show');

    Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger']);
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'payment']);
    Route::apiResource('suppliers', SupplierController::class);

    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('medicines/{medicine}/movements', [MedicineController::class, 'movements']);
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

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'update']);

    Route::apiResource('users', UserController::class)->except('show');
});
