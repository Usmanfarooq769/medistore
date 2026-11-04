import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../stores/auth';

const Blank = { render: () => null };
const admin = { admin: true };

// every page is lazy-loaded => small first download, fast start
const routes = [
    { path: '/login', component: () => import('../pages/Login.vue'), meta: { guest: true, blank: true } },
    { path: '/customer-portal', component: () => import('../pages/CustomerPortal.vue'), meta: { public: true, blank: true, title: 'Customer Portal' } },
    { path: '/', component: Blank },

    { path: '/dashboard', component: () => import('../pages/Dashboard.vue'), meta: { ...admin, title: 'Dashboard' } },
    { path: '/stage4', component: () => import('../pages/StageFour.vue'), meta: { title: 'Stage 4 Operations', stage4: true } },
    { path: '/stage5', component: () => import('../pages/StageFive.vue'), meta: { title: 'Stage 5 Enterprise Operations', stage5: true } },
    { path: '/stage6', component: () => import('../pages/StageSix.vue'), meta: { title: 'Stage 6 Operations', stage6: true } },
    { path: '/quality-governance', component: () => import('../pages/QualityManagement.vue'), meta: { title: 'Quality & Safety', quality: true } },
    { path: '/product-roadmap', component: () => import('../pages/ProductRoadmap.vue'), meta: { ...admin, title: 'Product Completion Roadmap' } },
    { path: '/enterprise-platform', component: () => import('../pages/EnterprisePlatform.vue'), meta: { ...admin, title: 'Enterprise Platform' } },
    { path: '/system-readiness', component: () => import('../pages/SystemReadiness.vue'), meta: { ...admin, title: 'Production Readiness' } },
    { path: '/pos', component: () => import('../pages/Pos.vue'), meta: { title: 'POS – New Sale' } },
    { path: '/mobile', component: () => import('../pages/MobileHome.vue'), meta: { title: 'Mobile Dashboard' } },
    { path: '/barcode-labels', component: () => import('../pages/BarcodeLabels.vue'), meta: { ...admin, title: 'Barcode Labels' } },
    { path: '/purchase-orders', component: () => import('../pages/PurchaseOrders.vue'), meta: { manager: true, title: 'Purchase Orders' } },
    { path: '/prescriptions', component: () => import('../pages/Prescriptions.vue'), meta: { title: 'Prescriptions' } },
    { path: '/branches', component: () => import('../pages/Branches.vue'), meta: { ...admin, title: 'Branches & Transfers' } },
    { path: '/finance', component: () => import('../pages/Finance.vue'), meta: { ...admin, title: 'Finance & Accounting' } },
    { path: '/promotions', component: () => import('../pages/Promotions.vue'), meta: { ...admin, title: 'Promotions & Coupons' } },
    { path: '/reports/reorder-suggestions', component: () => import('../pages/ReorderSuggestions.vue'), meta: { ...admin, title: 'Smart Reorder Suggestions' } },
    { path: '/reports/customer-aging', component: () => import('../pages/CustomerAging.vue'), meta: { ...admin, title: 'Customer Receivables Ageing' } },
    { path: '/cashier/closing', component: () => import('../pages/CashierClosing.vue'), meta: { title: 'Cashier Shift & Closing' } },
    { path: '/reports/batch-profitability', component: () => import('../pages/reports/BatchProfitability.vue'), meta: { manager: true, title: 'Batch Profitability' } },

    // Stock in
    { path: '/purchases', component: () => import('../pages/Purchases.vue'), meta: { manager: true, title: 'Purchases (Stock In)' } },
    { path: '/purchases/create', component: () => import('../pages/PurchaseCreate.vue'), meta: { manager: true, title: 'New Purchase' } },
    { path: '/purchases/:id', component: () => import('../pages/PurchaseShow.vue'), meta: { manager: true, title: 'Purchase' } },
    { path: '/suppliers', component: () => import('../pages/Suppliers.vue'), meta: { manager: true, title: 'Suppliers' } },
    { path: '/suppliers/:id', component: () => import('../pages/SupplierShow.vue'), meta: { manager: true, title: 'Supplier Ledger' } },

    // Inventory
    { path: '/medicines', component: () => import('../pages/Medicines.vue'), meta: { ...admin, title: 'Medicines' } },
    { path: '/medicines/:id', component: () => import('../pages/MedicineShow.vue'), meta: { ...admin, title: 'Stock Card' } },
    { path: '/categories', component: () => import('../pages/Categories.vue'), meta: { ...admin, title: 'Categories' } },
    { path: '/manufacturers', component: () => import('../pages/Manufacturers.vue'), meta: { ...admin, title: 'Companies' } },
    { path: '/adjustments', component: () => import('../pages/Adjustments.vue'), meta: { manager: true, title: 'Stock Adjustment' } },

    // Sales
    { path: '/sales', component: () => import('../pages/Sales.vue'), meta: { title: 'Sales / Invoices' } },
    { path: '/customers', component: () => import('../pages/Customers.vue'), meta: { title: 'Customers' } },
    { path: '/customers/:id', component: () => import('../pages/CustomerShow.vue'), meta: { title: 'Customer' } },

    // RETURNS
    { path: '/returns', component: () => import('../pages/returns/SaleReturns.vue'), meta: { title: 'Customer Returns' } },
    { path: '/returns/new', component: () => import('../pages/returns/SaleReturnCreate.vue'), meta: { title: 'New Customer Return' } },
    { path: '/supplier-returns', component: () => import('../pages/returns/PurchaseReturns.vue'), meta: { ...admin, title: 'Returns to Supplier' } },
    { path: '/supplier-returns/new', component: () => import('../pages/returns/PurchaseReturnCreate.vue'), meta: { ...admin, title: 'New Return to Supplier' } },

    // Alerts & reports
    { path: '/alerts/low-stock', component: () => import('../pages/LowStock.vue'), meta: { title: 'Low Stock' } },
    { path: '/alerts/expiry', component: () => import('../pages/Expiry.vue'), meta: { title: 'Expiry Alert' } },
    { path: '/reports/daily', component: () => import('../pages/reports/Daily.vue'), meta: { title: 'Daily Closing' } },
    { path: '/reports/summary', component: () => import('../pages/reports/Summary.vue'), meta: { manager: true, title: 'Sales & Profit' } },
    { path: '/reports/stock', component: () => import('../pages/reports/Stock.vue'), meta: { manager: true, title: 'Stock Report' } },

    // System
    { path: '/users', component: () => import('../pages/Users.vue'), meta: { ...admin, title: 'Users' } },
    { path: '/audit-logs', component: () => import('../pages/AuditLogs.vue'), meta: { ...admin, title: 'Audit Trail' } },
    { path: '/settings', component: () => import('../pages/Settings.vue'), meta: { ...admin, title: 'Settings' } },

    // Printing (no layout)
    { path: '/print/sale/:id', component: () => import('../pages/print/SaleReceipt.vue'), meta: { blank: true } },
    { path: '/print/return/:id', component: () => import('../pages/print/ReturnReceipt.vue'), meta: { blank: true } },

    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuth();

    if (to.meta.public) return true;
    if (to.meta.guest) return auth.token ? '/' : true;
    if (!auth.token) return { path: '/login', query: { redirect: to.fullPath } };

    if (!auth.user) {
        try { await auth.fetchMe(); } catch (e) { auth.clear(); return '/login'; }
    }

    if (to.path === '/') return auth.isAdmin ? '/dashboard' : (['branch_manager','pharmacist'].includes(auth.user?.role) ? '/mobile' : '/pos');
    if (to.meta.admin && !auth.isAdmin) return '/pos';
    if (to.meta.stage4 && !['admin', 'branch_manager'].includes(auth.user?.role)) return '/mobile';
    if (to.meta.stage5 && !['admin', 'branch_manager'].includes(auth.user?.role)) return '/mobile';
    if (to.meta.stage6 && !['admin', 'branch_manager'].includes(auth.user?.role)) return '/mobile';
    if (to.meta.quality && !['admin', 'branch_manager', 'pharmacist'].includes(auth.user?.role)) return '/mobile';
    if (to.meta.manager && !['admin', 'branch_manager'].includes(auth.user?.role)) return '/mobile';
    return true;
});

router.afterEach((to) => {
    document.title = `${to.meta.title ? to.meta.title + ' | ' : ''}${useAuth().settings.store_name || 'MediStore'}`;
});

export default router;
