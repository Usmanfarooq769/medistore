<template>
    <div>
        <!-- welcome -->
        <div class="card custom-card overflow-hidden mb-4 ms-welcome">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="fw-semibold mb-1">{{ t('Hello') }}, {{ auth.user?.name }} 👋</h4>
                    <div class="text-muted">{{ t('Here is what is happening in your pharmacy today.') }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span v-if="updatedAt" class="text-muted fs-12 d-none d-md-inline">Updated {{ updatedAt }}</span>
                    <button class="btn btn-light" :disabled="loading" @click="load"><i class="ri-refresh-line me-1" :class="{ 'ms-spin': loading }"></i>{{ t('Refresh') }}</button>
                </div>
            </div>
        </div>

        <!-- quick actions -->
        <div class="row g-3 mb-4">
            <div v-for="a in actions" :key="a.to" class="col-6 col-md">
                <router-link :to="a.to" class="card custom-card h-100 text-center text-decoration-none ms-action">
                    <div class="card-body py-3">
                        <span :class="['avatar avatar-md avatar-rounded mb-2', `bg-${a.color}-transparent`, `text-${a.color}`]"><i :class="['bi', a.icon, 'fs-5']"></i></span>
                        <div class="fw-medium fs-13 text-default">{{ t(a.label) }}</div>
                    </div>
                </router-link>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger d-flex justify-content-between align-items-center"><span><i class="bi bi-wifi-off me-2"></i>Could not load the dashboard.</span><button class="btn btn-sm btn-danger" @click="load">Try again</button></div>

        <!-- needs attention -->
        <div v-if="d && attention.length" class="card custom-card mb-4 border-warning-subtle">
            <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold me-2"><i class="bi bi-bell-fill text-warning me-1"></i>{{ t('Needs attention') }}</span>
                <router-link v-for="a in attention" :key="a.label" :to="a.to" class="badge rounded-pill text-decoration-none fs-12 py-2 px-3" :class="`bg-${a.color}-transparent`">{{ a.count }} {{ a.label }}</router-link>
            </div>
        </div>

        <!-- KPI groups -->
        <template v-for="g in groups" :key="g.title">
            <div class="fs-12 text-uppercase fw-semibold text-muted mb-2 mt-1" style="letter-spacing:.06rem">{{ g.title }}</div>
            <div class="row g-3 mb-4">
                <template v-if="!d"><div v-for="i in 4" :key="i" class="col-sm-6 col-xl-3"><div class="card custom-card"><div class="card-body"><div class="skeleton" style="height:56px"></div></div></div></div></template>
                <div v-else v-for="c in g.cards" :key="c.label" class="col-sm-6 col-xl-3">
                    <router-link :to="c.to" class="card custom-card h-100 text-decoration-none ms-kpi">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span :class="['avatar avatar-lg avatar-rounded flex-shrink-0', `bg-${c.color}-transparent`, `text-${c.color}`]"><i :class="['bi', c.icon, 'fs-4']"></i></span>
                            <div class="min-w-0">
                                <div class="text-muted fs-12">{{ c.label }}</div>
                                <div class="fs-20 fw-semibold text-default text-truncate">{{ c.value }}</div>
                                <div class="fs-11 text-muted text-truncate">
                                    <span v-if="c.delta !== undefined && c.delta !== null" :class="c.delta >= 0 ? 'text-success' : 'text-danger'"><i :class="['bi', c.delta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right']"></i> {{ Math.abs(c.delta) }}% </span>{{ c.sub }}
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>
            </div>
        </template>

        <!-- charts -->
        <div class="row g-3 mb-4" v-if="d">
            <div class="col-xl-8"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title">{{ t('Sales and profit') }}</div>
                    <div class="btn-group btn-group-sm"><button class="btn" :class="range === 7 ? 'btn-primary' : 'btn-light'" @click="range = 7">7 days</button><button class="btn" :class="range === 30 ? 'btn-primary' : 'btn-light'" @click="range = 30">30 days</button></div></div>
                <div class="card-body"><canvas ref="salesEl" height="110"></canvas></div></div></div>
            <div class="col-xl-4"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title">{{ t('Payment methods') }}</div>
                    <div class="btn-group btn-group-sm"><button class="btn" :class="payRange === 'today' ? 'btn-primary' : 'btn-light'" @click="payRange = 'today'">Today</button><button class="btn" :class="payRange === 'month' ? 'btn-primary' : 'btn-light'" @click="payRange = 'month'">Month</button></div></div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div v-if="!payTotal" class="text-center text-muted py-5"><i class="bi bi-pie-chart fs-1 d-block opacity-50"></i>No sales yet</div>
                    <div v-show="payTotal" style="max-width:240px;margin:auto;width:100%"><canvas ref="payEl"></canvas></div>
                    <div v-if="payTotal" class="mt-3 small">
                        <div v-for="(v, k) in payNow" :key="k" class="d-flex justify-content-between py-1 border-bottom"><span class="text-capitalize"><span class="legend-dot" :style="{ background: payColors[k] || '#8492b5' }"></span>{{ k }}</span><b>{{ money(v) }} <span class="text-muted fw-normal">({{ Math.round(v / payTotal * 100) }}%)</span></b></div>
                    </div>
                </div></div></div>
        </div>

        <div class="row g-3 mb-4" v-if="d">
            <div class="col-xl-6"><div class="card custom-card h-100"><div class="card-header"><div class="card-title">{{ t('Sales by hour') }} <span class="text-muted fw-normal fs-12">(today)</span></div></div><div class="card-body"><canvas ref="hourEl" height="120"></canvas></div></div></div>
            <div class="col-xl-3"><div class="card custom-card h-100"><div class="card-header"><div class="card-title">{{ t('Top selling') }}</div></div>
                <ul class="list-group list-group-flush">
                    <li v-for="(x, i) in d.top" :key="x.name" class="list-group-item d-flex align-items-center gap-2"><span class="avatar avatar-xs avatar-rounded bg-primary-transparent text-primary fw-semibold">{{ i + 1 }}</span><span class="flex-fill text-truncate">{{ x.name }}</span><span class="badge bg-primary-transparent">{{ x.qty }}</span></li>
                    <li v-if="!d.top.length" class="list-group-item text-muted text-center py-4">No sales yet</li>
                </ul></div></div>
            <div class="col-xl-3"><div class="card custom-card h-100"><div class="card-header"><div class="card-title">{{ t('Top categories') }}</div></div>
                <div class="card-body">
                    <div v-for="x in d.top_categories" :key="x.name" class="mb-3">
                        <div class="d-flex justify-content-between fs-12 mb-1"><span class="text-truncate">{{ x.name }}</span><b>{{ money(x.total) }}</b></div>
                        <div class="progress progress-xs"><div class="progress-bar" :style="{ width: (x.total / catMax * 100) + '%' }"></div></div>
                    </div>
                    <div v-if="!d.top_categories.length" class="text-muted text-center py-4">No sales yet</div>
                </div></div></div>
        </div>

        <div class="row g-3 mb-4" v-if="d">
            <div class="col-xl-7"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title">{{ t('Recent sales') }}</div><router-link to="/sales" class="btn btn-sm btn-light">{{ t('View all') }}</router-link></div>
                <div class="table-responsive"><table class="table text-nowrap table-hover mb-0">
                    <thead><tr><th>{{ t('Invoice') }}</th><th>{{ t('Customer') }}</th><th class="text-end">{{ t('Total') }}</th><th>{{ t('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="s in d.recent" :key="s.id"><td class="fw-semibold">{{ s.invoice_no }}</td><td>{{ s.customer_name || 'Walk-in' }}</td><td class="text-end">{{ money(s.total) }}</td>
                            <td><span class="badge text-capitalize" :class="pay(s.payment_status)">{{ s.payment_status }}</span></td>
                            <td class="text-end"><a class="btn btn-sm btn-icon btn-light" :href="`/print/sale/${s.id}`" target="_blank"><i class="bi bi-printer"></i></a></td></tr>
                        <tr v-if="!d.recent.length"><td colspan="5" class="text-center text-muted py-4">No sales yet</td></tr>
                    </tbody></table></div></div></div>
            <div class="col-xl-5"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title"><i class="bi bi-hourglass-split text-warning me-1"></i>{{ t('Expiring soon') }}</div><router-link to="/alerts/expiry" class="btn btn-sm btn-warning-light">{{ t('View all') }}</router-link></div>
                <ul class="list-group list-group-flush">
                    <li v-for="e in d.expiring_list" :key="e.medicine_id + e.batch_no" class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="min-w-0"><div class="fw-medium text-truncate">{{ e.name }} <small class="text-muted">{{ e.strength }}</small></div><div class="fs-11 text-muted">Batch {{ e.batch_no || '—' }} · {{ e.quantity }} units · {{ e.expiry_date?.slice(0, 10) }}</div></div>
                        <span class="badge" :class="e.days_left <= 14 ? 'bg-danger-transparent' : 'bg-warning-transparent'">{{ e.days_left }} d</span>
                    </li>
                    <li v-if="!d.expiring_list.length" class="list-group-item text-muted text-center py-4">Nothing is expiring soon 🎉</li>
                </ul></div></div>
        </div>

        <div class="row g-3" v-if="d">
            <div class="col-xl-4"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ t('Low stock') }}</div><router-link to="/alerts/low-stock" class="btn btn-sm btn-danger-light">{{ t('View all') }}</router-link></div>
                <ul class="list-group list-group-flush">
                    <li v-for="m in d.low" :key="m.id" class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0"><div class="text-truncate fw-medium">{{ m.name }} <small class="text-muted">{{ m.strength }}</small></div><div class="fs-11 text-muted">Re-order at {{ m.reorder_level }}</div></div>
                        <span class="badge" :class="m.total_stock <= 0 ? 'bg-danger-transparent' : 'bg-warning-transparent'">{{ m.total_stock }}</span>
                        <router-link class="btn btn-sm btn-icon btn-primary" :to="`/purchases/create?medicine=${m.id}`" title="Buy"><i class="bi bi-plus-lg"></i></router-link>
                    </li>
                    <li v-if="!d.low.length" class="list-group-item text-muted text-center py-4">All good 🎉</li>
                </ul></div></div>
            <div class="col-xl-4"><div class="card custom-card h-100">
                <div class="card-header"><div class="card-title">{{ t('Top customers') }} <span class="text-muted fw-normal fs-12">(30 days)</span></div></div>
                <ul class="list-group list-group-flush">
                    <li v-for="c in d.top_customers" :key="c.customer_id" class="list-group-item d-flex justify-content-between align-items-center">
                        <router-link :to="`/customers/${c.customer_id}`" class="text-decoration-none text-truncate">{{ c.name }}<div class="fs-11 text-muted">{{ c.invoices }} invoices</div></router-link><b>{{ money(c.total) }}</b></li>
                    <li v-if="!d.top_customers.length" class="list-group-item text-muted text-center py-4">No customer sales yet</li>
                </ul></div></div>
            <div class="col-xl-4"><div class="card custom-card h-100">
                <div class="card-header justify-content-between"><div class="card-title">{{ t('Recent purchases') }}</div><router-link to="/purchases" class="btn btn-sm btn-light">{{ t('View all') }}</router-link></div>
                <ul class="list-group list-group-flush">
                    <li v-for="p in d.recent_purchases" :key="p.id" class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="min-w-0"><div class="text-truncate fw-medium">{{ p.supplier || 'Supplier' }}</div><div class="fs-11 text-muted">{{ p.invoice_no || '—' }} · {{ p.date }}</div></div>
                        <div class="text-end"><b>{{ money(p.total) }}</b><div><span class="badge text-capitalize" :class="pay(p.payment_status)">{{ p.payment_status }}</span></div></div></li>
                    <li v-if="!d.recent_purchases.length" class="list-group-item text-muted text-center py-4">No purchases yet</li>
                </ul></div></div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import api from '../api';
import { useAuth } from '../stores/auth';
import { money } from '../utils/format';
import { t } from '../utils/i18n';

const pay = (s) => ({ paid: 'bg-success-transparent', partial: 'bg-warning-transparent', due: 'bg-danger-transparent', unpaid: 'bg-danger-transparent' }[String(s).toLowerCase()] || 'bg-secondary-transparent');

const auth = useAuth();
const d = ref(null);
const loading = ref(false);
const error = ref(false);
const updatedAt = ref('');
const range = ref(7);
const payRange = ref('today');
const salesEl = ref(null), payEl = ref(null), hourEl = ref(null);
let salesChart, payChart, hourChart;
const payColors = { cash: '#35bdaa', card: '#8b7eff', online: '#4aa3ff', credit: '#f5b849' };

const actions = [
    { to: '/pos', icon: 'bi-cart-plus', label: 'New Sale', color: 'primary' },
    { to: '/purchases/create', icon: 'bi-box-arrow-in-down', label: 'New Purchase', color: 'secondary' },
    { to: '/medicines?new=1', icon: 'bi-plus-square', label: 'Add Medicine', color: 'info' },
    { to: '/returns/new', icon: 'bi-arrow-counterclockwise', label: 'Customer Return', color: 'warning' },
    { to: '/reports/daily', icon: 'bi-calendar-check', label: 'Day Closing', color: 'secondary' },
];

const groups = computed(() => {
    const c = d.value?.cards;
    if (!c) return [{ title: 'Sales', cards: [] }];
    return [
        { title: 'Sales & profit', cards: [
            { label: t("Today's sales"), value: money(c.today_net), sub: `${c.today_invoices} invoices · returns ${money(c.today_returns)}`, delta: c.change_pct, icon: 'bi-cash-stack', color: 'primary', to: '/reports/daily' },
            { label: t("Today's profit"), value: money(c.today_profit), sub: `${t('Average bill')} ${money(c.avg_ticket)}`, icon: 'bi-graph-up-arrow', color: 'success', to: '/reports/daily' },
            { label: t('This month'), value: money(c.month_sales), sub: `Purchases ${money(c.month_purchase)}`, icon: 'bi-calendar3', color: 'info', to: '/reports/summary' },
            { label: t('Net profit (month)'), value: money(c.month_net_profit), sub: `Profit ${money(c.month_profit)} − expenses ${money(c.month_expenses)} · margin ${c.margin_pct}%`, icon: 'bi-piggy-bank', color: c.month_net_profit >= 0 ? 'success' : 'danger', to: '/reports/summary' },
        ] },
        { title: 'Stock health', cards: [
            { label: t('Stock value (cost)'), value: money(c.stock_value), sub: `Retail ${money(c.stock_sale_value)} · ${c.medicines} medicines`, icon: 'bi-boxes', color: 'secondary', to: '/reports/stock' },
            { label: t('Low stock'), value: c.low_stock, sub: `${c.out_of_stock} out of stock →`, icon: 'bi-exclamation-triangle', color: 'danger', to: '/alerts/low-stock' },
            { label: t('Expiring soon'), value: c.expiring, sub: `${money(c.expiring_value)} at risk →`, icon: 'bi-hourglass-split', color: 'warning', to: '/alerts/expiry' },
            { label: t('Expired'), value: c.expired, sub: `${money(c.expired_value)} to write off →`, icon: 'bi-x-octagon', color: 'danger', to: '/alerts/expiry?type=expired' },
        ] },
        { title: 'Money & customers', cards: [
            { label: t('Customer dues'), value: money(c.customer_due), sub: `${c.customers} customers`, icon: 'bi-wallet2', color: 'primary', to: '/customers?due=1' },
            { label: t('Supplier payable'), value: money(c.supplier_due), sub: 'Owed to suppliers', icon: 'bi-truck', color: 'warning', to: '/suppliers' },
            { label: t("Today's expenses"), value: money(c.today_expenses), sub: `Month ${money(c.month_expenses)}`, icon: 'bi-receipt-cutoff', color: 'secondary', to: '/finance' },
            { label: t('Dead stock'), value: c.dead_stock, sub: `${money(c.dead_stock_value)} not sold in 90 days`, icon: 'bi-archive', color: 'secondary', to: '/reports/stock' },
        ] },
    ];
});

const attention = computed(() => {
    const p = d.value?.pending || {};
    return [
        { count: p.prescriptions, label: 'prescriptions to review', to: '/prescriptions', color: 'warning' },
        { count: p.online_orders, label: 'online orders', to: '/stage6?tab=orders', color: 'info' },
        { count: p.return_requests, label: 'return requests', to: '/customer-return-requests', color: 'warning' },
        { count: p.purchase_orders, label: 'purchase orders open', to: '/purchase-orders', color: 'secondary' },
        { count: p.open_shifts, label: 'open cashier shifts', to: '/cashier/closing', color: 'secondary' },
        { count: p.fbr_pending, label: 'FBR invoices pending', to: '/sales', color: 'danger' },
    ].filter((x) => x.count > 0);
});

const payNow = computed(() => Object.fromEntries(Object.entries((payRange.value === 'today' ? d.value?.payments_today : d.value?.payments_month) || {}).filter(([, v]) => v > 0)));
const payTotal = computed(() => Object.values(payNow.value).reduce((s, v) => s + v, 0));
const catMax = computed(() => Math.max(1, ...(d.value?.top_categories || []).map((x) => Number(x.total))));

const grid = { color: 'rgba(132,146,181,.15)' };
function drawSales() {
    salesChart?.destroy();
    if (!salesEl.value || !d.value) return;
    const c = range.value === 7 ? d.value.chart : d.value.chart30;
    salesChart = new Chart(salesEl.value, {
        data: { labels: c.labels, datasets: [
            { type: 'bar', label: 'Sales', data: c.values, backgroundColor: 'rgba(139,126,255,.85)', borderRadius: 6, maxBarThickness: 30, order: 2 },
            { type: 'line', label: 'Profit', data: c.profit, borderColor: '#35bdaa', backgroundColor: '#35bdaa', tension: .35, pointRadius: 3, order: 1 },
        ] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } }, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${money(x.parsed.y)}` } } }, scales: { y: { beginAtZero: true, grid }, x: { grid: { display: false } } } },
    });
}
function drawPay() {
    payChart?.destroy();
    if (!payEl.value || !payTotal.value) return;
    const keys = Object.keys(payNow.value);
    payChart = new Chart(payEl.value, { type: 'doughnut', data: { labels: keys, datasets: [{ data: keys.map((k) => payNow.value[k]), backgroundColor: keys.map((k) => payColors[k] || '#8492b5'), borderWidth: 0 }] }, options: { cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (x) => `${x.label}: ${money(x.parsed)}` } } } } });
}
function drawHours() {
    hourChart?.destroy();
    if (!hourEl.value || !d.value) return;
    const h = d.value.hourly;
    const from = Math.max(0, Math.min(8, h.values.findIndex((v) => v > 0) === -1 ? 8 : h.values.findIndex((v) => v > 0)));
    hourChart = new Chart(hourEl.value, { type: 'bar', data: { labels: h.labels.slice(from, 23), datasets: [{ data: h.values.slice(from, 23), backgroundColor: 'rgba(53,189,170,.8)', borderRadius: 4 }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (x) => money(x.parsed.y) } } }, scales: { y: { beginAtZero: true, grid }, x: { grid: { display: false } } } } });
}

async function load() {
    loading.value = true; error.value = false;
    try {
        d.value = (await api.get('/dashboard', { silent: true })).data;
        updatedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        await nextTick(); drawSales(); drawPay(); drawHours();
    } catch (e) { error.value = true; } finally { loading.value = false; }
}

watch(range, drawSales);
watch([payRange, d], () => nextTick(drawPay));
onMounted(load);
onBeforeUnmount(() => { salesChart?.destroy(); payChart?.destroy(); hourChart?.destroy(); });
</script>
