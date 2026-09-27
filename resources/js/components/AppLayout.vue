<template>
    <aside class="sidebar" :class="{ show: sideOpen }">
        <div class="brand"><i class="bi bi-capsule-pill me-2"></i>{{ auth.settings.store_name || 'MediStore' }}</div>
        <nav class="nav flex-column py-2">
            <template v-for="item in menu" :key="item.to || item.section">
                <div v-if="item.section" class="nav-section">{{ item.section }}</div>
                <router-link v-else :to="item.to" class="nav-link">
                    <i :class="['bi', item.icon]"></i> {{ item.label }}
                    <span v-if="item.badge && item.badge()" class="badge ms-auto" :class="item.badgeClass">{{ item.badge() }}</span>
                </router-link>
            </template>
        </nav>
    </aside>
    <div v-if="sideOpen" class="sidebar-backdrop d-lg-none" @click="sideOpen = false"></div>

    <div class="main">
        <header class="topbar d-flex align-items-center justify-content-between px-3 px-md-4 py-2 sticky-top">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="btn btn-light d-lg-none" @click="sideOpen = !sideOpen"><i class="bi bi-list"></i></button>
                <h5 class="mb-0 fw-semibold text-truncate">{{ $route.meta.title }}</h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small d-none d-xl-inline me-2"><i class="bi bi-calendar3 me-1"></i>{{ todayLabel }}</span>
                <button class="btn btn-light" @click="toggleTheme" title="Dark / light"><i :class="['bi', dark ? 'bi-sun' : 'bi-moon-stars']"></i></button>

                <div class="position-relative" ref="bellEl">
                    <button class="btn btn-light position-relative" @click="bellOpen = !bellOpen" aria-label="Alerts">
                        <i class="bi bi-bell"></i>
                        <span v-if="bellCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ bellCount }}</span>
                    </button>
                    <div v-if="bellOpen" class="dropdown-menu dropdown-menu-end show shadow p-0" style="width:310px; right:0; left:auto; top:115%">
                        <div class="px-3 py-2 border-bottom fw-semibold">Low stock</div>
                        <router-link v-for="m in alerts.counts.low_items" :key="m.id" class="dropdown-item d-flex justify-content-between"
                                     :to="auth.isAdmin ? `/purchases/create?medicine=${m.id}` : '/alerts/low-stock'" @click="bellOpen = false">
                            <span class="text-truncate me-2">{{ m.name }} {{ m.strength }}</span>
                            <span class="badge" :class="m.total_stock <= 0 ? 'bg-danger' : 'bg-warning text-dark'">{{ m.total_stock }}</span>
                        </router-link>
                        <div v-if="!alerts.counts.low_items.length" class="p-3 small text-muted">All stock levels are fine 🎉</div>
                        <router-link v-if="alerts.counts.expired" to="/alerts/expiry?type=expired" class="dropdown-item small text-danger border-top" @click="bellOpen = false">
                            <i class="bi bi-x-octagon me-1"></i>{{ alerts.counts.expired }} expired batch(es)
                        </router-link>
                        <router-link v-if="alerts.counts.expiring" to="/alerts/expiry" class="dropdown-item small text-warning-emphasis" @click="bellOpen = false">
                            <i class="bi bi-hourglass-split me-1"></i>{{ alerts.counts.expiring }} batch(es) expiring soon
                        </router-link>
                    </div>
                </div>

                <router-link to="/pos" class="btn btn-success"><i class="bi bi-cart-plus"></i><span class="d-none d-sm-inline ms-1">New Sale</span></router-link>

                <div class="position-relative">
                    <button class="btn btn-light" @click="userOpen = !userOpen"><i class="bi bi-person-circle"></i> <span class="d-none d-md-inline">{{ auth.user?.name }}</span></button>
                    <div v-if="userOpen" class="dropdown-menu dropdown-menu-end show shadow" style="right:0; left:auto; top:115%" @mouseleave="userOpen = false">
                        <span class="dropdown-item-text small text-muted text-capitalize">{{ auth.user?.role }}</span>
                        <hr class="dropdown-divider">
                        <button class="dropdown-item text-danger" @click="logout"><i class="bi bi-box-arrow-right me-1"></i> Logout</button>
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
const dark = ref(document.documentElement.getAttribute('data-bs-theme') === 'dark');
const todayLabel = new Date().toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

const bellCount = computed(() => alerts.counts.low_stock + alerts.counts.expired);
const expiryCount = () => alerts.counts.expiring + alerts.counts.expired;

const menu = computed(() => {
    const a = auth.isAdmin;
    return [
        a && { to: '/dashboard', icon: 'bi-speedometer2', label: 'Dashboard' },
        { to: '/pos', icon: 'bi-cart3', label: 'POS / Sell' },
        a && { section: 'Stock In' },
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
        { section: 'Reports & Alerts' },
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
    await auth.logout();
    router.push('/login');
}

// alert polling: every 2 min + after any stock change, paused when tab hidden
let timer;
const onStock = () => setTimeout(alerts.load, 300);
const onKey = (e) => { if (e.key === 'F1') { e.preventDefault(); router.push('/pos'); } };
const onClick = (e) => { if (bellEl.value && !bellEl.value.contains(e.target)) bellOpen.value = false; };
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
            icon: 'warning', title: 'Stock Alert',
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
