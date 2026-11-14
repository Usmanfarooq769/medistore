<template>
    <div class="page">
        <!-- ================= HEADER ================= -->
        <header class="app-header">
            <div class="main-header-container container-fluid">
                <div class="header-content-left">
                    <div class="header-element d-lg-none ms-2">
                        <router-link to="/" class="ms-logo-mark bg-primary text-white"><i class="bi bi-capsule-pill"></i></router-link>
                    </div>
                    <div class="header-element mx-lg-0 mx-2">
                        <a aria-label="Toggle sidebar" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" href="javascript:void(0);" @click="toggleSidebar"><span></span></a>
                    </div>
                    <div class="header-element d-none d-md-flex align-items-center position-relative" ref="searchWrap" style="min-width:min(380px,34vw)">
                        <div class="input-group input-group-sm ms-2 w-100">
                            <span class="input-group-text bg-transparent"><i class="ri-search-line"></i></span>
                            <input ref="searchEl" v-model="searchQ" class="form-control" :placeholder="t('Search medicines, customers, invoices…')" autocomplete="off" @focus="searchOpen = true" @input="runSearch" @keydown.down.prevent="moveSearch(1)" @keydown.up.prevent="moveSearch(-1)" @keydown.enter.prevent="goSearch()">
                            <span class="input-group-text bg-transparent d-none d-lg-flex"><kbd class="fs-10">Ctrl K</kbd></span>
                        </div>
                        <div v-if="searchOpen && searchQ.trim().length >= 2" class="dropdown-menu show main-header-dropdown p-1 w-100" style="top:calc(100% + 6px);max-height:60vh;overflow:auto">
                            <div v-if="searching" class="px-3 py-2 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>
                            <a v-for="(r, i) in searchRes" :key="r.type + r.to" href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 rounded" :class="{ active: i === searchIdx }" @mouseenter="searchIdx = i" @click="goSearch(r)">
                                <span class="avatar avatar-xs avatar-rounded bg-primary-transparent text-primary flex-shrink-0"><i :class="['bi', r.icon]"></i></span>
                                <span class="min-w-0"><span class="d-block text-truncate fw-medium">{{ r.title }}</span><span class="d-block text-truncate fs-11 opacity-75">{{ r.type }} · {{ r.subtitle }}</span></span>
                            </a>
                            <div v-if="!searching && !searchRes.length" class="px-3 py-3 text-center text-muted small">No results for “{{ searchQ }}”</div>
                        </div>
                    </div>
                </div>

                <div class="header-content-right">
                    <!-- language -->
                    <div class="header-element">
                        <a href="javascript:void(0);" class="header-link fw-semibold" title="English / اردو" @click="toggleLang"><span class="fs-13">{{ lang === 'en' ? 'اردو' : 'EN' }}</span></a>
                    </div>
                    <!-- theme mode -->
                    <div class="header-element header-theme-mode">
                        <a href="javascript:void(0);" class="header-link" :title="dark ? 'Light mode' : 'Dark mode'" aria-label="Toggle theme" @click="toggleTheme">
                            <i :class="dark ? 'ri-sun-line' : 'ri-moon-line'" class="header-link-icon fs-20"></i>
                        </a>
                    </div>

                    <!-- alerts -->
                    <div class="header-element position-relative" ref="bellEl">
                        <a href="javascript:void(0);" class="header-link" aria-label="Alerts" :aria-expanded="bellOpen" @click="bellOpen = !bellOpen; userOpen = false">
                            <i class="ri-notification-3-line header-link-icon fs-20"></i>
                            <span v-if="bellCount" class="badge bg-danger rounded-pill header-icon-badge">{{ bellCount > 99 ? '99+' : bellCount }}</span>
                        </a>
                        <div v-if="bellOpen" class="dropdown-menu show menu-panel main-header-dropdown p-0" style="width: 330px">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                <span class="fw-semibold">Low stock</span>
                                <router-link to="/alerts/low-stock" class="small text-primary text-decoration-none fw-semibold" @click="bellOpen = false">View all</router-link>
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

                    <div v-if="auth.isAdmin && branches.length > 1" class="header-element d-none d-sm-flex">
                        <select v-model="activeBranchId" class="form-select form-select-sm" style="max-width:170px" title="Active branch" @change="switchBranch"><option v-for="b in branches" :key="b.id" :value="String(b.id)">{{b.name}}</option></select>
                    </div>

                    <div class="header-element">
                        <router-link to="/pos" class="btn btn-primary btn-wave d-inline-flex align-items-center">
                            <i class="ri-shopping-cart-2-line"></i><span class="d-none d-sm-inline ms-2">{{ t('New sale') }}</span>
                        </router-link>
                    </div>

                    <!-- profile -->
                    <div class="header-element position-relative" ref="userEl">
                        <a href="javascript:void(0);" class="header-link" :aria-expanded="userOpen" @click="userOpen = !userOpen; bellOpen = false">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm avatar-rounded bg-primary-transparent text-primary fw-semibold">{{ initials }}</span>
                                <div class="d-xl-block d-none ms-2 lh-1 text-start">
                                    <span class="fw-semibold fs-13 d-block">{{ auth.user?.name }}</span>
                                    <span class="fs-11 text-muted text-capitalize">{{ (auth.user?.role || '').replace('_', ' ') }}</span>
                                </div>
                            </div>
                        </a>
                        <div v-if="userOpen" class="dropdown-menu show menu-panel main-header-dropdown">
                            <div class="menu-head">
                                <div class="text-truncate">{{ auth.user?.name }}</div>
                                <div class="small fw-normal text-muted text-capitalize">{{ (auth.user?.role || '').replace('_', ' ') }}</div>
                            </div>
                            <hr class="dropdown-divider">
                            <router-link v-if="auth.isAdmin" to="/settings" class="dropdown-item" @click="userOpen = false"><i class="ri-settings-3-line me-2"></i>Settings</router-link>
                            <button class="dropdown-item text-danger" @click="logout"><i class="ri-logout-box-r-line me-2"></i>{{ t('Log out') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ================= SIDEBAR ================= -->
        <aside class="app-sidebar sticky" id="sidebar">
            <div class="main-sidebar-header">
                <router-link to="/" class="header-logo d-flex align-items-center gap-2 text-decoration-none">
                    <span class="ms-logo-mark"><i class="bi bi-capsule-pill"></i></span>
                    <span class="min-w-0 ms-logo-text">
                        <span class="d-block fw-bold fs-16 lh-1 text-truncate">{{ auth.settings.store_name || 'MediStore' }}</span>
                        <span class="d-block fs-10 text-muted mt-1">Pharmacy management</span>
                    </span>
                </router-link>
            </div>

            <div class="main-sidebar" id="sidebar-scroll">
                <nav class="main-menu-container nav nav-pills flex-column sub-open">
                    <ul class="main-menu">
                        <template v-for="item in menu" :key="item.to || item.group || item.section">
                            <li v-if="item.section" class="slide__category"><span class="category-name">{{ t(item.section) }}</span></li>

                            <li v-else-if="item.group" class="slide has-sub" :class="{ open: openGroup === item.group }">
                                <a href="javascript:void(0);" class="side-menu__item" :class="{ active: openGroup === item.group }" @click="toggleGroup(item.group)">
                                    <i :class="['bi', item.icon, 'side-menu__icon']"></i>
                                    <span class="side-menu__label">{{ t(item.group) }}</span>
                                    <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1" :style="{ display: openGroup === item.group ? 'block' : 'none' }">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">{{ t(item.group) }}</a></li>
                                    <li v-for="c in item.children" :key="c.to" class="slide">
                                        <router-link :to="c.to" class="side-menu__item" active-class="" exact-active-class="active">{{ t(c.label) }}</router-link>
                                    </li>
                                </ul>
                            </li>

                            <li v-else class="slide">
                                <router-link :to="item.to" class="side-menu__item" active-class="" exact-active-class="active">
                                    <i :class="['bi', item.icon, 'side-menu__icon']"></i>
                                    <span class="side-menu__label">{{ t(item.label) }}</span>
                                    <span v-if="item.badge && item.badge()" class="badge rounded-pill ms-auto" :class="item.badgeClass">{{ item.badge() }}</span>
                                </router-link>
                            </li>
                        </template>
                    </ul>
                </nav>
            </div>
        </aside>
        <div id="responsive-overlay" :class="{ active: sideOpen }" @click="closeSidebar"></div>

        <!-- ================= CONTENT ================= -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between mt-4 mb-3 page-header-breadcrumb">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><router-link to="/">{{ auth.settings.store_name || 'MediStore' }}</router-link></li>
                            <li v-if="crumbGroup" class="breadcrumb-item">{{ t(crumbGroup) }}</li>
                            <li class="breadcrumb-item active" aria-current="page">{{ t(pageTitle) }}</li>
                        </ol>
                    </nav>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
import api from '../api';
import { t, lang, toggleLang, applyLang } from '../utils/i18n';
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

/* ---------- global search (Ctrl+K) ---------- */
const searchQ = ref(''), searchRes = ref([]), searchOpen = ref(false), searching = ref(false), searchIdx = ref(0);
const searchEl = ref(null), searchWrap = ref(null);
let searchTimer = null, searchSeq = 0;
function runSearch() {
    clearTimeout(searchTimer);
    if (searchQ.value.trim().length < 2) { searchRes.value = []; return; }
    searching.value = true;
    searchTimer = setTimeout(async () => {
        const seq = ++searchSeq;
        try { const { data } = await api.get('/search', { params: { q: searchQ.value.trim() }, silent: true }); if (seq === searchSeq) { searchRes.value = data.data; searchIdx.value = 0; } }
        catch (e) { if (seq === searchSeq) searchRes.value = []; } finally { if (seq === searchSeq) searching.value = false; }
    }, 250);
}
function moveSearch(n) { if (searchRes.value.length) searchIdx.value = (searchIdx.value + n + searchRes.value.length) % searchRes.value.length; }
function goSearch(r = searchRes.value[searchIdx.value]) { if (!r) return; searchOpen.value = false; searchQ.value = ''; searchRes.value = []; router.push(r.to); }

const bellOpen = ref(false);
const userOpen = ref(false);
const bellEl = ref(null);
const userEl = ref(null);
const branches = ref([]);
const activeBranchId = ref(localStorage.getItem('active_branch_id') || '1');
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
    const manager = auth.user?.role === 'branch_manager';
    const pharmacist = auth.user?.role === 'pharmacist';
    const am = a || manager;
    const amp = a || manager || pharmacist;
    return [
        { section: 'Main' },
        a && { to: '/dashboard', icon: 'bi-speedometer2', label: 'Dashboard' },
        { to: '/pos', icon: 'bi-cart3', label: 'POS / Sell' },
        { to: '/cashier/closing', icon: 'bi-safe', label: 'Cashier Shift & Closing' },
        { to: '/mobile', icon: 'bi-phone', label: 'Mobile View' },

        am && { section: 'Stock in' },
        am && { to: '/purchases/create', icon: 'bi-box-arrow-in-down', label: 'New Purchase' },
        am && { to: '/purchases', icon: 'bi-journal-text', label: 'Purchase List' },
        am && { to: '/purchase-orders', icon: 'bi-clipboard-check', label: 'Purchase Orders' },
        am && { to: '/suppliers', icon: 'bi-truck', label: 'Suppliers' },

        a && { section: 'Inventory' },
        a && { to: '/medicines', icon: 'bi-capsule', label: 'Medicines' },
        a && { to: '/barcode-labels', icon: 'bi-upc', label: 'Barcode Labels' },
        a && { to: '/categories', icon: 'bi-tags', label: 'Categories' },
        a && { to: '/manufacturers', icon: 'bi-building', label: 'Manufacturers' },
        am && { to: '/adjustments', icon: 'bi-sliders', label: 'Stock Adjustment' },

        { section: 'Sales' },
        { to: '/sales', icon: 'bi-receipt', label: 'Sales / Invoices' },
        { to: '/customers', icon: 'bi-people', label: 'Customers' },
        { to: '/prescriptions', icon: 'bi-file-medical', label: 'Prescriptions' },
        a && { to: '/promotions', icon: 'bi-ticket-perforated', label: 'Promotions & Coupons' },

        { section: 'Returns' },
        { to: '/returns/new', icon: 'bi-arrow-counterclockwise', label: 'New Customer Return' },
        { to: '/returns', icon: 'bi-arrow-return-left', label: 'Customer Returns' },
        amp && { to: '/customer-return-requests', icon: 'bi-inbox', label: 'Online Return Requests' },
        a && { to: '/supplier-returns', icon: 'bi-truck-flatbed', label: 'Return to Supplier' },

        am && { section: 'Pharmacy operations' },
        a && { group: 'Head Office', icon: 'bi-buildings', children: [
            { to: '/stage4?tab=chain', label: 'Branch Performance' },
            { to: '/branches', label: 'Branches & Transfers' },
        ] },
        am && { group: 'Procurement', icon: 'bi-bag-check', children: [
            { to: '/stage5?tab=procurement', label: 'Supplier Quotations' },
            { to: '/stage6?tab=suppliers', label: 'Supplier Contracts' },
            { to: '/stage4?tab=schemes', label: 'Manufacturer Schemes' },
            { to: '/stage4?tab=expiry', label: 'Expiry Claims to Supplier' },
        ] },
        am && { group: 'Warehouse & Cold Chain', icon: 'bi-snow2', children: [
            { to: '/stage5?tab=warehouse', label: 'Warehouse & Stock Count' },
            { to: '/stage6?tab=coldchain', label: 'Cold Chain Monitoring' },
        ] },
        am && { group: 'Online Orders', icon: 'bi-bag-heart', children: [
            { to: '/stage6?tab=orders', label: 'Order Requests' },
            { to: '/stage6?tab=delivery', label: 'Delivery Tracking' },
        ] },
        am && { group: 'Doctors & Insurance', icon: 'bi-hospital', children: [
            { to: '/stage4?tab=doctors', label: 'Doctors & Clinics' },
            { to: '/stage5?tab=healthcare', label: 'Facilities & Refills' },
            { to: '/stage4?tab=insurance', label: 'Insurance Claims' },
            { to: '/stage5?tab=insurance', label: 'Insurance Eligibility' },
        ] },
        amp && { group: 'Quality & Compliance', icon: 'bi-shield-check', children: [
            { to: '/quality-governance?tab=incidents', label: 'Medication Safety' },
            { to: '/quality-governance?tab=documents', label: 'Controlled Documents' },
            { to: '/quality-governance?tab=audits', label: 'Compliance Audits' },
            ...(auth.perm('controlled.register') ? [{ to: '/controlled-register', label: 'Controlled Register' }] : []),
            { to: '/interactions', label: 'Drug Interactions' },
            ...(am ? [{ to: '/stage5?tab=compliance', label: 'Recalls & Controlled Drugs' }] : []),
        ] },
        am && { group: 'Customer Engagement', icon: 'bi-megaphone', children: [
            { to: '/stage5?tab=engagement', label: 'Campaigns & Refill Reminders' },
        ] },
        am && { group: 'Analytics & AI', icon: 'bi-graph-up-arrow', children: [
            { to: '/stage4?tab=analytics', label: 'Sales Forecast' },
            { to: '/stage5?tab=analytics', label: 'Demand Forecast' },
            { to: '/stage4?tab=ai', label: 'AI Assistant' },
        ] },

        { section: 'Reports & alerts' },
        { to: '/reports/daily', icon: 'bi-calendar-check', label: 'Daily Closing' },
        am && { to: '/reports/summary', icon: 'bi-graph-up', label: 'Sales & Profit' },
        am && { to: '/reports/batch-profitability', icon: 'bi-bar-chart-line', label: 'Batch Profitability' },
        am && { to: '/reports/stock', icon: 'bi-boxes', label: 'Stock Report' },
        a && { to: '/reports/reorder-suggestions', icon: 'bi-cart-check', label: 'Smart Reorder' },
        a && { to: '/reports/customer-aging', icon: 'bi-hourglass-split', label: 'Customer Ageing' },
        { to: '/alerts/low-stock', icon: 'bi-exclamation-triangle', label: 'Low Stock', badge: () => alerts.counts.low_stock, badgeClass: 'bg-danger' },
        { to: '/alerts/expiry', icon: 'bi-calendar-x', label: 'Expiry', badge: expiryCount, badgeClass: 'bg-warning text-dark' },

        a && { section: 'Administration' },
        a && { to: '/finance', icon: 'bi-calculator', label: 'Finance & Accounting' },
        a && { to: '/users', icon: 'bi-person-gear', label: 'Users & Roles' },
        a && { to: '/audit-logs', icon: 'bi-journal-check', label: 'Audit Trail' },
        a && { group: 'Platform', icon: 'bi-diagram-3', children: [
            { to: '/enterprise-platform?tab=partners', label: 'Partner APIs' },
            { to: '/enterprise-platform?tab=subscriptions', label: 'Branch Subscriptions' },
            { to: '/enterprise-platform?tab=notifications', label: 'Notification Centre' },
            { to: '/product-roadmap', label: 'Product Roadmap' },
            { to: '/system-readiness', label: 'System Health' },
        ] },
        a && { to: '/settings', icon: 'bi-gear', label: 'Settings' },
    ].filter(Boolean);
});

// accordion state: the group that contains the current page is opened automatically
const openGroup = ref(null);
const syncOpenGroup = () => {
    const g = menu.value.find(i => i.group && i.children.some(c => c.to === route.fullPath));
    if (g) openGroup.value = g.group;
};
const toggleGroup = (name) => { openGroup.value = openGroup.value === name ? null : name; };

const pageTitle = computed(() => {
    for (const i of menu.value) {
        if (i.to === route.fullPath) return i.label;
        const c = i.children?.find(x => x.to === route.fullPath);
        if (c) return c.label;
    }
    return route.meta.title;
});

function toggleTheme() {
    dark.value = !dark.value;
    const t = dark.value ? 'dark' : 'light';
    const h = document.documentElement;
    ['data-bs-theme', 'data-theme-mode', 'data-header-styles', 'data-menu-styles'].forEach(a => h.setAttribute(a, t));
    localStorage.setItem('theme', t);
}

// Rixzo sidebar: data-toggled="close" hides it, "open" shows it (mobile overlay)
const html = document.documentElement;
const isMobile = () => window.innerWidth < 992;
function toggleSidebar() {
    if (isMobile()) { sideOpen.value = !sideOpen.value; return; }
    if (html.getAttribute('data-toggled') === 'close') html.removeAttribute('data-toggled');
    else html.setAttribute('data-toggled', 'close');
}
function closeSidebar() { sideOpen.value = false; }
function applyMobileState() {
    if (isMobile()) html.setAttribute('data-toggled', sideOpen.value ? 'open' : 'close');
    else if (html.getAttribute('data-toggled') === 'open') html.removeAttribute('data-toggled');
}
watch(sideOpen, applyMobileState);
const onResize = () => { if (!isMobile()) sideOpen.value = false; applyMobileState(); };

const crumbGroup = computed(() => menu.value.find(i => i.group && i.children.some(c => c.to === route.fullPath))?.group || '');

async function switchBranch() {
    localStorage.setItem('active_branch_id', activeBranchId.value);
    window.location.reload();
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
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); searchEl.value?.focus(); searchEl.value?.select(); searchOpen.value = true; }
    if (e.key === 'Escape') { bellOpen.value = false; userOpen.value = false; sideOpen.value = false; searchOpen.value = false; }
};
const onClick = (e) => {
    if (bellEl.value && !bellEl.value.contains(e.target)) bellOpen.value = false;
    if (userEl.value && !userEl.value.contains(e.target)) userOpen.value = false;
    if (searchWrap.value && !searchWrap.value.contains(e.target)) searchOpen.value = false;
};
const onVisible = () => { if (!document.hidden) alerts.load(); };

onMounted(async () => {
    applyLang();
    syncOpenGroup();
    applyMobileState();
    window.addEventListener('resize', onResize);
    if (auth.isAdmin) { try { const { data } = await (await import('../api')).default.get('/branches'); branches.value = data; if (!branches.value.some(b => String(b.id) === activeBranchId.value)) { activeBranchId.value = String(branches.value[0]?.id || 1); localStorage.setItem('active_branch_id', activeBranchId.value); } } catch (e) {} }
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
            showCancelButton: true, confirmButtonText: 'View low stock', cancelButtonText: 'Later', confirmButtonColor: '#8b7eff',
        });
        if (r.isConfirmed) router.push('/alerts/low-stock');
    }
});

onBeforeUnmount(() => {
    html.removeAttribute('data-toggled');
    window.removeEventListener('resize', onResize);
    clearInterval(timer);
    window.removeEventListener('stock-changed', onStock);
    window.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onClick);
    document.removeEventListener('visibilitychange', onVisible);
});

watch(() => route.fullPath, () => { syncOpenGroup(); sideOpen.value = false; bellOpen.value = false; userOpen.value = false; });
</script>