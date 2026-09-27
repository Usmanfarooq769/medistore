import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../stores/auth';

const Blank = { render: () => null };
const admin = { admin: true };

// every page is lazy-loaded => small first download, fast start
const routes = [
    { path: '/login', component: () => import('../pages/Login.vue'), meta: { guest: true, blank: true } },
    { path: '/', component: Blank },

    { path: '/dashboard', component: () => import('../pages/Dashboard.vue'), meta: { ...admin, title: 'Dashboard' } },
    { path: '/pos', component: () => import('../pages/Pos.vue'), meta: { title: 'POS – New Sale' } },

    // Stock in
    { path: '/purchases', component: () => import('../pages/Purchases.vue'), meta: { ...admin, title: 'Purchases (Stock In)' } },
    { path: '/purchases/create', component: () => import('../pages/PurchaseCreate.vue'), meta: { ...admin, title: 'New Purchase' } },
    { path: '/purchases/:id', component: () => import('../pages/PurchaseShow.vue'), meta: { ...admin, title: 'Purchase' } },
    { path: '/suppliers', component: () => import('../pages/Suppliers.vue'), meta: { ...admin, title: 'Suppliers' } },
    { path: '/suppliers/:id', component: () => import('../pages/SupplierShow.vue'), meta: { ...admin, title: 'Supplier Ledger' } },

    // Inventory
    { path: '/medicines', component: () => import('../pages/Medicines.vue'), meta: { ...admin, title: 'Medicines' } },
    { path: '/medicines/:id', component: () => import('../pages/MedicineShow.vue'), meta: { ...admin, title: 'Stock Card' } },
    { path: '/categories', component: () => import('../pages/Categories.vue'), meta: { ...admin, title: 'Categories' } },
    { path: '/manufacturers', component: () => import('../pages/Manufacturers.vue'), meta: { ...admin, title: 'Companies' } },
    { path: '/adjustments', component: () => import('../pages/Adjustments.vue'), meta: { ...admin, title: 'Stock Adjustment' } },

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
    { path: '/reports/summary', component: () => import('../pages/reports/Summary.vue'), meta: { ...admin, title: 'Sales & Profit' } },
    { path: '/reports/stock', component: () => import('../pages/reports/Stock.vue'), meta: { ...admin, title: 'Stock Report' } },

    // System
    { path: '/users', component: () => import('../pages/Users.vue'), meta: { ...admin, title: 'Users' } },
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

    if (to.meta.guest) return auth.token ? '/' : true;
    if (!auth.token) return { path: '/login', query: { redirect: to.fullPath } };

    if (!auth.user) {
        try { await auth.fetchMe(); } catch (e) { auth.clear(); return '/login'; }
    }

    if (to.path === '/') return auth.isAdmin ? '/dashboard' : '/pos';
    if (to.meta.admin && !auth.isAdmin) return '/pos';
    return true;
});

router.afterEach((to) => {
    document.title = `${to.meta.title ? to.meta.title + ' | ' : ''}${useAuth().settings.store_name || 'MediStore'}`;
});

export default router;
