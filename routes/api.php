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
use App\Http\Controllers\Api\StageFourController;
use App\Http\Controllers\Api\StageFiveController;
use App\Http\Controllers\Api\StageSixController;
use App\Http\Controllers\Api\MedicationSafetyController;
use App\Http\Controllers\Api\DocumentGovernanceController;
use App\Http\Controllers\Api\ComplianceAuditController;
use App\Http\Controllers\Api\ProductRoadmapController;
use App\Http\Controllers\Api\PartnerIntegrationController;
use App\Http\Controllers\Api\CustomerPortalController;
use App\Http\Controllers\Api\NotificationCenterController;
use App\Http\Controllers\Api\SubscriptionManagementController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\SafetyController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ControlledRegisterController;
use Illuminate\Support\Facades\Route;

/* All routes are prefixed with /api (bootstrap/app.php) */

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/* ---------------- Admin + Branch Manager + Cashier ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('lookups', LookupController::class);
    Route::get('search', SearchController::class)->middleware('throttle:60,1');

    // Drug safety, substitutes, interaction reference, FBR retry, controlled register
    Route::post('pos/safety-check', [SafetyController::class, 'check']);
    Route::get('medicines/{medicine}/substitutes', [SafetyController::class, 'substitutes']);
    Route::get('interactions', [SafetyController::class, 'interactions']);
    Route::middleware('role:admin,branch_manager,pharmacist')->group(function () {
        Route::post('interactions', [SafetyController::class, 'storeInteraction']);
        Route::put('interactions/{interaction}', [SafetyController::class, 'updateInteraction']);
        Route::delete('interactions/{interaction}', [SafetyController::class, 'destroyInteraction']);
    });
    Route::get('reports/controlled-register', ControlledRegisterController::class);
    Route::delete('sales/{sale}', [SaleController::class, 'destroy']);     // admin / users with sales.void / manager PIN
    Route::post('sales/{sale}/fbr-sync', function (\Illuminate\Http\Request $request, \App\Models\Sale $sale) {
        abort_unless(in_array($request->user()->role, ['admin', 'branch_manager'], true), 403);
        abort_unless((int) $sale->branch_id === \App\Services\BranchContext::id(), 404);
        $sale = app(\App\Services\FbrService::class)->issue($sale);
        return response()->json(['message' => 'FBR status: ' . $sale->fbr_status, 'data' => ['fbr_status' => $sale->fbr_status, 'fbr_invoice_no' => $sale->fbr_invoice_no, 'fbr_error' => $sale->fbr_error]]);
    });

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

    // Pharmacists, branch managers and cashiers can upload/view; qualified roles review.
    Route::get('tax-profiles', [\App\Http\Controllers\Api\TaxProfileController::class, 'index']);
    Route::get('promotions/active', [\App\Http\Controllers\Api\PromotionController::class, 'active']);
    Route::post('promotions/preview', [\App\Http\Controllers\Api\PromotionController::class, 'preview']);
    Route::get('prescriptions', [PrescriptionController::class, 'index']);
    Route::get('prescriptions/find/{prescriptionNo}', [PrescriptionController::class, 'find']);
    Route::get('prescriptions/{prescription}/file', [PrescriptionController::class, 'file']);
    Route::post('prescriptions', [PrescriptionController::class, 'store']);

    // Customers
    Route::get('customers/{customer}/sales', [CustomerController::class, 'sales']);
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement']);
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

    // Customer self-service return requests; the actual inventory return is still posted through SaleReturnController.
    Route::get('customer-return-requests', [\App\Http\Controllers\Api\CustomerReturnRequestController::class, 'index'])->middleware('role:admin,branch_manager,pharmacist');
    Route::post('customer-return-requests/{returnRequest}/review', [\App\Http\Controllers\Api\CustomerReturnRequestController::class, 'review'])->middleware('role:admin,branch_manager,pharmacist');
    Route::post('customer-return-requests/{returnRequest}/complete', [\App\Http\Controllers\Api\CustomerReturnRequestController::class, 'complete'])->middleware('role:admin,branch_manager,pharmacist');
});

/* ---------------- Admin only ---------------- */
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('dashboard', DashboardController::class);

    // Purchase orders and receiving workflow

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
    Route::get('reports/reorder-suggestions', [ReportController::class, 'reorderSuggestions']);
    Route::get('reports/customer-aging', [ReportController::class, 'customerAging']);
    Route::apiResource('promotions', \App\Http\Controllers\Api\PromotionController::class)->except(['show']);
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


    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('medicines/{medicine}/movements', [MedicineController::class, 'movements']);
    Route::post('medicines/{medicine}/convert-stock-units', [MedicineController::class, 'convertStockUnits']);
    Route::get('medicines/import-template', [MedicineController::class, 'downloadImportTemplate']);
    Route::post('medicines/import-csv', [MedicineController::class, 'importCsv']);
    Route::apiResource('medicines', MedicineController::class);


    // RETURN TO SUPPLIER (stock out)
    Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store']);
    Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show']);


    Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'update']);

    Route::get('permission-catalog', [UserController::class, 'permissionCatalog']);
    Route::apiResource('users', UserController::class)->except('show');
});


Route::post('prescriptions/{prescription}/review', [PrescriptionController::class, 'review'])->middleware(['auth:sanctum','role:admin,branch_manager,pharmacist']);


/* ---------------- Branch manager operational permissions ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,branch_manager'])->group(function () {
    Route::get('purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'store']);
    Route::get('purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'show']);
    Route::post('purchase-orders/{purchaseOrder}/review', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'receive']);

    Route::get('medicines', [MedicineController::class, 'index']);
    Route::get('medicines/{medicine}', [MedicineController::class, 'show']);
    Route::apiResource('purchases', PurchaseController::class)->except('update');
    Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger']);
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'payment']);
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('adjustments', [StockAdjustmentController::class, 'store']);
    Route::post('adjustments/write-off-expired', [StockAdjustmentController::class, 'writeOffExpired']);
    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('reports/stock', [ReportController::class, 'stock']);
    Route::get('reports/batch-profitability', [ReportController::class, 'batchProfitability']);
});

/* ---------------- Stage 4: chain operations, clinics, schemes, insurance, analytics, mobile and AI ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->prefix('stage4')->group(function () {
    // Mobile uses the same authenticated branch-aware workflows as the web app.
    Route::get('mobile/overview', [StageFourController::class, 'mobileOverview']);
    Route::post('ai/ask', [StageFourController::class, 'aiAssistant'])->middleware('throttle:10,1');

    Route::get('doctors', [StageFourController::class, 'doctors']);
    Route::get('clinics', [StageFourController::class, 'clinics']);
    Route::get('referrals', [StageFourController::class, 'referrals']);
    Route::post('referrals', [StageFourController::class, 'createReferral']);
    Route::patch('prescriptions/{prescription}/doctor', [StageFourController::class, 'linkPrescription']);

    Route::get('expiry-claims', [StageFourController::class, 'expiryClaims']);
    Route::post('expiry-claims', [StageFourController::class, 'createExpiryClaim']);
    Route::post('expiry-claims/{claim}/submit', [StageFourController::class, 'submitExpiryClaim']);

    Route::get('insurance-claims', [StageFourController::class, 'insuranceClaims']);
    Route::post('insurance-claims', [StageFourController::class, 'createInsuranceClaim']);
    Route::post('insurance-claims/{claim}/submit', [StageFourController::class, 'submitInsuranceClaim']);
});

Route::middleware(['auth:sanctum', 'role:admin,branch_manager'])->prefix('stage4')->group(function () {
    Route::get('lookups', [StageFourController::class, 'lookups']);
    Route::post('doctors', [StageFourController::class, 'saveDoctor']);
    Route::put('doctors/{doctor}', [StageFourController::class, 'saveDoctor']);
    Route::delete('doctors/{doctor}', [StageFourController::class, 'deleteDoctor']);
    Route::post('clinics', [StageFourController::class, 'saveClinic']);
    Route::put('clinics/{clinic}', [StageFourController::class, 'saveClinic']);

    Route::get('schemes', [StageFourController::class, 'schemes']);
    Route::post('schemes', [StageFourController::class, 'saveScheme']);
    Route::put('schemes/{scheme}', [StageFourController::class, 'saveScheme']);
    Route::post('schemes/preview', [StageFourController::class, 'schemePreview']);
    Route::post('schemes/redeem', [StageFourController::class, 'redeemScheme']);
    Route::get('schemes/profitability', [StageFourController::class, 'schemeProfitability']);

    Route::post('expiry-claims/{claim}/review', [StageFourController::class, 'reviewExpiryClaim']);
    Route::post('expiry-claims/{claim}/credit', [StageFourController::class, 'creditExpiryClaim']);
    Route::get('insurance-providers', [StageFourController::class, 'insuranceProviders']);
    Route::post('insurance-providers', [StageFourController::class, 'saveInsuranceProvider']);
    Route::put('insurance-providers/{provider}', [StageFourController::class, 'saveInsuranceProvider']);
    Route::post('insurance-plans', [StageFourController::class, 'saveInsurancePlan']);
    Route::put('insurance-plans/{plan}', [StageFourController::class, 'saveInsurancePlan']);
    Route::post('insurance-coverages', [StageFourController::class, 'saveInsuranceCoverage']);
    Route::post('insurance-claims/{claim}/review', [StageFourController::class, 'reviewInsuranceClaim']);
    Route::post('insurance-claims/{claim}/settle', [StageFourController::class, 'settleInsuranceClaim']);

    Route::get('analytics', [StageFourController::class, 'analytics']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('stage4')->group(function () {
    Route::get('chain/overview', [StageFourController::class, 'chainOverview']);
});


/* ---------------- Stage 5.1–5.4: enterprise pharmacy operations ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->prefix('stage5')->group(function () {
    Route::get('overview', [StageFiveController::class, 'overview']);
    Route::post('refills', [StageFiveController::class, 'requestRefill']);
    Route::get('push-subscriptions', fn () => response()->json(['supported' => true]));
    Route::post('push-subscriptions', [StageFiveController::class, 'registerPushSubscription']);
});

Route::middleware(['auth:sanctum', 'role:admin,branch_manager'])->prefix('stage5')->group(function () {
    Route::get('lookups', [StageFiveController::class, 'lookups']);
    // 5.1 Central procurement and warehouse
    Route::get('quotations', [StageFiveController::class, 'quotations']);
    Route::get('quotations/comparison', [StageFiveController::class, 'quotationComparison']);
    Route::post('quotations', [StageFiveController::class, 'storeQuotation']);
    Route::post('quotations/{quotation}/review', [StageFiveController::class, 'quotationReview']);
    Route::post('quotations/{quotation}/convert', [StageFiveController::class, 'quotationConvert']);
    Route::get('warehouse/locations', [StageFiveController::class, 'locations']);
    Route::post('warehouse/locations', [StageFiveController::class, 'storeLocation']);
    Route::post('warehouse/allocate', [StageFiveController::class, 'assignBatchLocation']);
    Route::get('warehouse/counts', [StageFiveController::class, 'stockCounts']);
    Route::post('warehouse/counts', [StageFiveController::class, 'storeStockCount']);
    Route::post('warehouse/counts/{count}/review', [StageFiveController::class, 'reviewStockCount']);

    // 5.2 Hospitals, clinics and healthcare integrations
    Route::get('facilities', [StageFiveController::class, 'facilities']);
    Route::get('facilities/{facility}/api-tokens', [StageFiveController::class, 'facilityTokens']);
    Route::post('facilities/{facility}/api-token', [StageFiveController::class, 'createFacilityToken']);
    Route::delete('facilities/{facility}/api-tokens/{token}', [StageFiveController::class, 'revokeFacilityToken']);
    Route::post('facilities', [StageFiveController::class, 'saveFacility']);
    Route::put('facilities/{facility}', [StageFiveController::class, 'saveFacility']);

    // 5.3 Insurance administration, recalls and compliance
    Route::get('insurance/eligibility', [StageFiveController::class, 'eligibilityChecks']);
    Route::post('insurance/eligibility', [StageFiveController::class, 'checkEligibility']);
    Route::get('insurance/claims/{claim}/events', [StageFiveController::class, 'claimEvents']);
    Route::post('insurance/claims/{claim}/events', [StageFiveController::class, 'recordClaimEvent']);
    Route::patch('compliance/medicines/{medicine}/controlled', [StageFiveController::class, 'setControlledMedicine']);
    Route::get('compliance/recalls', [StageFiveController::class, 'recalls']);
    Route::get('compliance/recall-items', [StageFiveController::class, 'recallItems']);
    Route::post('compliance/recalls', [StageFiveController::class, 'createRecall']);
    Route::post('compliance/recalls/{recall}/close', [StageFiveController::class, 'closeRecall']);
    Route::post('compliance/recall-items/{item}/release', [StageFiveController::class, 'releaseRecallBatch']);

    // 5.4 Customer engagement and business intelligence
    Route::patch('customers/{customer}/marketing-consent', [StageFiveController::class, 'setCustomerMarketingConsent']);
    Route::get('campaigns', [StageFiveController::class, 'campaigns']);
    Route::post('campaigns', [StageFiveController::class, 'saveCampaign']);
    Route::post('campaigns/{campaign}/send', [StageFiveController::class, 'sendCampaign'])->middleware('throttle:5,1');
    Route::get('analytics/seasonality', [StageFiveController::class, 'seasonality']);
    Route::put('campaigns/{campaign}', [StageFiveController::class, 'saveCampaign']);
    Route::get('reminders', [StageFiveController::class, 'reminders']);
    Route::post('reminders', [StageFiveController::class, 'createReminder']);
    Route::get('analytics/forecast', [StageFiveController::class, 'forecast']);
    Route::post('analytics/forecast/save', [StageFiveController::class, 'saveForecast']);
});

Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist'])->prefix('stage5')->group(function () {
    Route::get('compliance/controlled-register', [StageFiveController::class, 'controlledRegister']);
    Route::post('compliance/controlled-register', [StageFiveController::class, 'recordControlledMovement']);
    Route::get('refills', [StageFiveController::class, 'refills']);
    Route::post('refills/{refill}/review', [StageFiveController::class, 'reviewRefill']);
});

Route::post('integrations/facilities/{facility}/prescriptions', [StageFiveController::class, 'receiveFacilityPrescription'])->middleware('throttle:30,1');


/* ---------------- Stage 6: storefront, delivery, cold-chain and supplier operations ---------------- */
Route::get('storefront/branches', [StageSixController::class, 'publicBranches'])->middleware('throttle:30,1');
Route::get('storefront/catalog', [StageSixController::class, 'publicCatalog'])->middleware('throttle:60,1');
Route::post('storefront/track-order', [StageSixController::class, 'trackOnlineOrder'])->middleware('throttle:15,1');
Route::post('storefront/orders', [StageSixController::class, 'placeOnlineOrder'])->middleware('throttle:8,1');

Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->prefix('stage6')->group(function () {
    Route::get('overview', [StageSixController::class, 'overview']);
    Route::get('online-orders', [StageSixController::class, 'onlineOrders']);
    Route::get('online-orders/{order}', [StageSixController::class, 'onlineOrderDetails']);
    Route::post('online-orders/{order}/review', [StageSixController::class, 'reviewOnlineOrder'])->middleware('role:admin,branch_manager');
    Route::post('online-orders/{order}/link-sale', [StageSixController::class, 'linkSale'])->middleware('role:admin,branch_manager,cashier');
    Route::get('deliveries', [StageSixController::class, 'deliveries']);
    Route::post('online-orders/{order}/delivery', [StageSixController::class, 'assignDelivery'])->middleware('role:admin,branch_manager');
    Route::patch('deliveries/{delivery}', [StageSixController::class, 'updateDelivery']);

    Route::get('cold-chain/devices', [StageSixController::class, 'coldChainDevices']);
    Route::post('cold-chain/devices', [StageSixController::class, 'saveColdChainDevice'])->middleware('role:admin,branch_manager');
    Route::put('cold-chain/devices/{device}', [StageSixController::class, 'saveColdChainDevice'])->middleware('role:admin,branch_manager');
    Route::get('cold-chain/logs', [StageSixController::class, 'coldChainLogs']);
    Route::post('cold-chain/devices/{device}/readings', [StageSixController::class, 'recordTemperature']);
    Route::post('cold-chain/logs/{log}/acknowledge', [StageSixController::class, 'acknowledgeExcursion'])->middleware('role:admin,branch_manager,pharmacist');

    Route::get('supplier-contracts', [StageSixController::class, 'supplierContracts'])->middleware('role:admin,branch_manager');
    Route::post('supplier-contracts', [StageSixController::class, 'saveSupplierContract'])->middleware('role:admin,branch_manager');
    Route::put('supplier-contracts/{contract}', [StageSixController::class, 'saveSupplierContract'])->middleware('role:admin,branch_manager');
    Route::get('supplier-performance', [StageSixController::class, 'supplierPerformance'])->middleware('role:admin,branch_manager');
    Route::get('event-logs', [StageSixController::class, 'eventLogs'])->middleware('role:admin,branch_manager');
});


/* ---------------- Quality, safety, document control and product governance ---------------- */
Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->prefix('quality')->group(function () {
    Route::get('overview', [MedicationSafetyController::class, 'overview']);
    Route::get('incidents', [MedicationSafetyController::class, 'index']);
    Route::post('incidents', [MedicationSafetyController::class, 'store']);
    Route::get('incidents/{incident}', [MedicationSafetyController::class, 'show']);
    Route::post('incidents/{incident}/actions', [MedicationSafetyController::class, 'storeAction'])->middleware('role:admin,branch_manager,pharmacist');
    Route::patch('actions/{action}', [MedicationSafetyController::class, 'updateAction'])->middleware('role:admin,branch_manager,pharmacist');
    Route::patch('incidents/{incident}/review', [MedicationSafetyController::class, 'review'])->middleware('role:admin,branch_manager,pharmacist');
    Route::post('incidents/{incident}/close', [MedicationSafetyController::class, 'close'])->middleware('role:admin,branch_manager,pharmacist');

    Route::get('documents', [DocumentGovernanceController::class, 'index'])->middleware('role:admin,branch_manager,pharmacist');
    Route::post('documents', [DocumentGovernanceController::class, 'store'])->middleware('role:admin,branch_manager');
    Route::post('documents/{document}/revisions', [DocumentGovernanceController::class, 'revise'])->middleware('role:admin,branch_manager');
    Route::post('documents/{document}/approve', [DocumentGovernanceController::class, 'approve'])->middleware('role:admin,branch_manager');
    Route::post('documents/{document}/retire', [DocumentGovernanceController::class, 'retire'])->middleware('role:admin');
    Route::get('documents/{document}/revisions/{revision}/download', [DocumentGovernanceController::class, 'download'])->middleware('role:admin,branch_manager,pharmacist');

    Route::get('audits', [ComplianceAuditController::class, 'index'])->middleware('role:admin,branch_manager');
    Route::post('audits', [ComplianceAuditController::class, 'store'])->middleware('role:admin,branch_manager');
    Route::patch('audits/{audit}/items/{item}', [ComplianceAuditController::class, 'updateItem'])->middleware('role:admin,branch_manager');
    Route::post('audits/{audit}/close', [ComplianceAuditController::class, 'close'])->middleware('role:admin,branch_manager');
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('product-roadmap')->group(function () {
    Route::get('/', [ProductRoadmapController::class, 'index']);
    Route::patch('milestones/{milestone}', [ProductRoadmapController::class, 'updateMilestone']);
    Route::patch('tasks/{task}', [ProductRoadmapController::class, 'updateTask']);
});


/* ---------------- Stage 8: customer self-service and partner ecosystem ---------------- */
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('enterprise')->group(function () {
    Route::get('partners', [PartnerIntegrationController::class, 'index']);
    Route::post('partners', [PartnerIntegrationController::class, 'store']);
    Route::patch('partners/{partner}', [PartnerIntegrationController::class, 'update']);
    Route::get('partners/{partner}/webhooks', [PartnerIntegrationController::class, 'endpoints']);
    Route::post('partners/{partner}/webhooks', [PartnerIntegrationController::class, 'storeEndpoint']);
    Route::patch('partners/{partner}/webhooks/{endpoint}', [PartnerIntegrationController::class, 'updateEndpoint']);
    Route::get('partners/{partner}/deliveries', [PartnerIntegrationController::class, 'deliveries']);
    Route::get('subscription-plans', [SubscriptionManagementController::class, 'plans']);
    Route::post('subscription-plans', [SubscriptionManagementController::class, 'savePlan']);
    Route::put('subscription-plans/{plan}', [SubscriptionManagementController::class, 'savePlan']);
    Route::get('subscriptions', [SubscriptionManagementController::class, 'subscriptions']);
    Route::post('subscriptions', [SubscriptionManagementController::class, 'assign']);
    Route::post('subscriptions/{subscription}/invoices', [SubscriptionManagementController::class, 'createInvoice']);
    Route::post('subscription-invoices/{invoice}/record-payment', [SubscriptionManagementController::class, 'recordPayment']);
    Route::post('customers/{customer}/portal-invitation', [CustomerPortalController::class, 'invite']);
});

Route::middleware(['partner.api', 'throttle:120,1'])->prefix('partner/v1')->group(function () {
    Route::get('catalog', [PartnerIntegrationController::class, 'partnerCatalog'])->middleware('partner.scope:catalog:read');
    Route::get('orders/{orderNo}', [PartnerIntegrationController::class, 'partnerOrder'])->middleware('partner.scope:orders:read');
    Route::post('orders', [PartnerIntegrationController::class, 'partnerCreateOrder'])->middleware('partner.scope:orders:write');
});

Route::prefix('customer-portal')->group(function () {
    Route::post('redeem-invitation', [CustomerPortalController::class, 'redeem'])->middleware('throttle:5,1');
    Route::post('login', [CustomerPortalController::class, 'login'])->middleware('throttle:8,1');
    Route::middleware('customer.portal')->group(function () {
        Route::post('logout', [CustomerPortalController::class, 'logout']);
        Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::get('orders', [CustomerPortalController::class, 'orders']);
        Route::get('invoices', [CustomerPortalController::class, 'invoices']);
        Route::get('invoices/{sale}', [CustomerPortalController::class, 'invoiceDetails']);
        Route::get('return-requests', [CustomerPortalController::class, 'returnRequests']);
        Route::post('return-requests', [CustomerPortalController::class, 'createReturnRequest'])->middleware('throttle:5,1');
        Route::post('orders', [CustomerPortalController::class, 'placeOrder'])->middleware('throttle:10,1');
    });
});

Route::middleware(['auth:sanctum', 'role:admin,branch_manager,pharmacist,cashier'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationCenterController::class, 'index']);
    Route::post('announcements', [NotificationCenterController::class, 'storeAnnouncement'])->middleware('role:admin');
    Route::patch('{notification}/read', [NotificationCenterController::class, 'markRead']);
    Route::post('read-all', [NotificationCenterController::class, 'markAllRead']);
});

/* ---------------- Stage 9: production readiness and operational assurance ---------------- */
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('system-readiness')->group(function () {
    Route::get('overview', [SystemHealthController::class, 'overview']);
    Route::post('run-checks', [SystemHealthController::class, 'runChecks']);
    Route::get('releases', [SystemHealthController::class, 'releases']);
    Route::post('releases', [SystemHealthController::class, 'storeRelease']);
    Route::get('recovery-drills', [SystemHealthController::class, 'recoveryDrills']);
    Route::post('recovery-drills', [SystemHealthController::class, 'storeRecoveryDrill']);
    Route::get('security-events', [SystemHealthController::class, 'securityEvents']);
});
