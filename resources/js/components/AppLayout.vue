<template>
    <aside class="sidebar" :class="{ show: sideOpen }">
        <div class="brand">
            <div class="brand-mark"><i class="bi bi-capsule-pill"></i></div>
            <div class="min-w-0">
                <div class="brand-name text-truncate">{{ auth.settings.store_name || 'MediStore' }}</div>
                <div class="brand-sub">Pharmacy management</div>
            </div>
        </div>

        <nav class="nav flex-column py-2">
            <template v-for="item in menu" :key="item.to || item.section">
                <div v-if="item.section" class="nav-section">{{ item.section }}</div>
                <router-link v-else :to="item.to" class="nav-link">
                    <i :class="['bi', item.icon]"></i>
                    <span class="text-truncate">{{ item.label }}</span>
                    <span v-if="item.badge && item.badge()" class="badge rounded-pill ms-auto" :class="item.badgeClass">{{ item.badge() }}</span>
                </router-link>
            </template>
        </nav>

        <div class="sidebar-foot">
            Press <kbd>F1</kbd> anytime to start a new sale
        </div>
    </aside>
    <div v-if="sideOpen" class="sidebar-backdrop d-lg-none" @click="sideOpen = false"></div>

    <div class="main">
        <header class="topbar d-flex align-items-center justify-content-between px-3 px-md-4 py-2 sticky-top">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="icon-btn d-lg-none" aria-label="Open menu" @click="sideOpen = !sideOpen"><i class="bi bi-list fs-5"></i></button>
                <h5 class="page-title mb-0 text-truncate">{{ $route.meta.title }}</h5>
            </div>

            <div class="d-flex align-items-center gap-1 gap-sm-2">
                <span class="date-chip d-none d-xl-inline-flex me-1"><i class="bi bi-calendar3"></i>{{ todayLabel }}</span>

                <button class="icon-btn" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'" aria-label="Toggle theme" @click="toggleTheme">
                    <i :class="['bi', dark ? 'bi-sun' : 'bi-moon-stars']"></i>
                </button>

                <!-- Alerts -->
                <div class="position-relative" ref="bellEl">
                    <button class="icon-btn" aria-label="Alerts" :aria-expanded="bellOpen" @click="bellOpen = !bellOpen; userOpen = false">
                        <i class="bi bi-bell"></i>
                        <span v-if="bellCount" class="count">{{ bellCount > 99 ? '99+' : bellCount }}</span>
                    </button>

                    <div v-if="bellOpen" class="dropdown-menu show menu-panel p-0" style="width: 330px">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <span class="fw-bold">Low stock</span>
                            <router-link to="/alerts/low-stock" class="small text-success text-decoration-none fw-semibold" @click="bellOpen = false">View all</router-link>
                        </div>

                        <div class="p-1" style="max-height: 300px; overflow-y: auto">
                            <router-link v-for="m in alerts.counts.low_items" :key="m.id" class="dropdown-item alert-row justify-content-between"
                                         :to="auth.isAdmin ? `/purchases/create?medicine=${m.id}` : '/alerts/low-stock'" @click="bellOpen = false">
                                <span class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="dot" :class="m.total_stock <= 0 ? 'bg-danger' : 'bg-warning'"></span>
                                    <span class="text-truncate">{{ m.name }} {{ m.strength }}</span>
                                </span>
                                <span class="badge rounded-pill" :class="m.total_stock <= 0 ? 'bg-danger' : 'bg-warning text-dark'">{{ m.total_stock }}</span>
                            </router-link>
                        </div>

                        <div v-if="!alerts.counts.low_items.length" class="state-box py-4">
                            <div class="state-icon" style="width:48px;height:48px;font-size:1.25rem"><i class="bi bi-check2-circle text-success"></i></div>
                            <div class="small">All stock levels are healthy.</div>
                        </div>

                        <div v-if="alerts.counts.expired || alerts.counts.expiring" class="p-1 border-top">
                            <router-link v-if="alerts.counts.expired" to="/alerts/expiry?type=expired" class="dropdown-item small text-danger" @click="bellOpen = false">
                                <i class="bi bi-x-octagon me-2"></i>{{ alerts.counts.expired }} expired batch(es)
                            </router-link>
                            <router-link v-if="alerts.counts.expiring" to="/alerts/expiry" class="dropdown-item small text-warning-emphasis" @click="bellOpen = false">
                                <i class="bi bi-hourglass-split me-2"></i>{{ alerts.counts.expiring }} batch(es) expiring soon
                            </router-link>
                        </div>
                    </div>
                </div>

                <router-link to="/pos" class="btn btn-success d-inline-flex align-items-center">
                    <i class="bi bi-cart-plus"></i><span class="d-none d-sm-inline ms-2">New sale</span>
                </router-link>

                <!-- User -->
                <div class="position-relative" ref="userEl">
                    <button class="user-btn" :aria-expanded="userOpen" @click="userOpen = !userOpen; bellOpen = false">
                        <span class="avatar">{{ initials }}</span>
                        <span class="d-none d-md-inline">{{ auth.user?.name }}</span>
                        <i class="bi bi-chevron-down small d-none d-md-inline text-muted"></i>
                    </button>

                    <div v-if="userOpen" class="dropdown-menu show menu-panel">
                        <div class="menu-head">
                            <div class="text-truncate">{{ auth.user?.name }}</div>
                            <div class="small fw-normal text-muted text-capitalize">{{ auth.user?.role }}</div>
                        </div>
                        <hr class="dropdown-divider">
                        <button class="dropdown-item text-danger" @click="logout"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-3 p-md-4"><slot /></main>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';
import { useAlerts } from '../stores/alerts';
import { Swal } from '../utils/swal';

const auth = useAuth();
const alerts = useAlerts();
const route = useRoute();
const router = useRouter();

const sideOpen = ref(false);
const bellOpen = ref(false);
const userOpen = ref(false);
const bellEl = ref(null);
const userEl = ref(null);
const dark = ref(document.documentElement.getAttribute('data-bs-theme') === 'dark');
const todayLabel = new Date().toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

const bellCount = computed(() => alerts.counts.low_stock + alerts.counts.expired);
const expiryCount = () => alerts.counts.expiring + alerts.counts.expired;

const initials = computed(() => {
    const parts = (auth.user?.name || '?').trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
});

const menu = computed(() => {
    const a = auth.isAdmin;
    return [
        a && { to: '/dashboard', icon: 'bi-speedometer2', label: 'Dashboard' },
        { to: '/pos', icon: 'bi-cart3', label: 'POS / Sell' },
        a && { section: 'Stock in' },
        a && { to: '/purchases/create', icon: 'bi-box-arrow-in-down', label: 'New Purchase' },
        a && { to: '/purchases', icon: 'bi-journal-text', label: 'Purchase List' },
        a && { to: '/suppliers', icon: 'bi-truck', label: 'Suppliers' },
        a && { section: 'Inventory' },
        a && { to: '/medicines', icon: 'bi-capsule', label: 'Medicines' },
        a && { to: '/categories', icon: 'bi-tags', label: 'Categories' },
        a && { to: '/manufacturers', icon: 'bi-building', label: 'Companies' },
        a && { to: '/adjustments', icon: 'bi-sliders', label: 'Stock Adjustment' },
        { section: 'Sales' },
        { to: '/sales', icon: 'bi-receipt', label: 'Sales / Invoices' },
        { to: '/customers', icon: 'bi-people', label: 'Customers' },
        { section: 'Returns' },
        { to: '/returns/new', icon: 'bi-arrow-counterclockwise', label: 'New Customer Return' },
        { to: '/returns', icon: 'bi-arrow-return-left', label: 'Customer Returns' },
        a && { to: '/supplier-returns', icon: 'bi-truck-flatbed', label: 'Return to Supplier' },
        { section: 'Reports & alerts' },
        { to: '/reports/daily', icon: 'bi-calendar-check', label: 'Daily Closing' },
        a && { to: '/reports/summary', icon: 'bi-graph-up', label: 'Sales & Profit' },
        a && { to: '/reports/stock', icon: 'bi-boxes', label: 'Stock Report' },
        { to: '/alerts/low-stock', icon: 'bi-exclamation-triangle', label: 'Low Stock', badge: () => alerts.counts.low_stock, badgeClass: 'bg-danger' },
        { to: '/alerts/expiry', icon: 'bi-calendar-x', label: 'Expiry', badge: expiryCount, badgeClass: 'bg-warning text-dark' },
        a && { section: 'System' },
        a && { to: '/users', icon: 'bi-person-gear', label: 'Users' },
        a && { to: '/settings', icon: 'bi-gear', label: 'Settings' },
    ].filter(Boolean);
});

function toggleTheme() {
    dark.value = !dark.value;
    const t = dark.value ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('theme', t);
}

async function logout() {
    userOpen.value = false;
    await auth.logout();
    router.push('/login');
}

// alert polling: every 2 min + after any stock change, paused when tab hidden
let timer;
const onStock = () => setTimeout(alerts.load, 300);
const onKey = (e) => {
    if (e.key === 'F1') { e.preventDefault(); router.push('/pos'); }
    if (e.key === 'Escape') { bellOpen.value = false; userOpen.value = false; sideOpen.value = false; }
};
const onClick = (e) => {
    if (bellEl.value && !bellEl.value.contains(e.target)) bellOpen.value = false;
    if (userEl.value && !userEl.value.contains(e.target)) userOpen.value = false;
};
const onVisible = () => { if (!document.hidden) alerts.load(); };

onMounted(async () => {
    await alerts.load();
    timer = setInterval(alerts.load, 120000);
    window.addEventListener('stock-changed', onStock);
    window.addEventListener('keydown', onKey);
    document.addEventListener('click', onClick);
    document.addEventListener('visibilitychange', onVisible);

    const c = alerts.counts;
    if ((c.low_stock || c.expired) && !sessionStorage.getItem('stockAlertShown') && route.path !== '/pos') {
        sessionStorage.setItem('stockAlertShown', '1');
        const r = await Swal.fire({
            icon: 'warning', title: 'Stock alert',
            html: `<b>${c.low_stock}</b> medicine(s) low on stock<br><b>${c.expired}</b> expired batch(es)<br><b>${c.expiring}</b> expiring soon`,
            showCancelButton: true, confirmButtonText: 'View low stock', cancelButtonText: 'Later', confirmButtonColor: '#198754',
        });
        if (r.isConfirmed) router.push('/alerts/low-stock');
    }
});

onBeforeUnmount(() => {
    clearInterval(timer);
    window.removeEventListener('stock-changed', onStock);
    window.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onClick);
    document.removeEventListener('visibilitychange', onVisible);
});

watch(() => route.fullPath, () => { sideOpen.value = false; bellOpen.value = false; userOpen.value = false; });
</script>