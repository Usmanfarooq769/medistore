<template>
    <div>
        <!-- welcome strip -->
        <div class="card custom-card overflow-hidden mb-4 ms-welcome">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="fw-semibold mb-1">Hello, {{ auth.user?.name }} 👋</h4>
                    <div class="text-muted">Here is what is happening in your pharmacy today.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-light" @click="load"><i class="ri-refresh-line me-1"></i>Refresh</button>
                </div>
            </div>
        </div>

        <!-- quick actions -->
        <div class="row g-3 mb-4">
            <div v-for="a in actions" :key="a.to" class="col-6 col-md">
                <router-link :to="a.to" class="card custom-card h-100 text-center text-decoration-none ms-action">
                    <div class="card-body py-3">
                        <span :class="['avatar avatar-md avatar-rounded mb-2', `bg-${a.color}-transparent`, `text-${a.color}`]"><i :class="['bi', a.icon, 'fs-5']"></i></span>
                        <div class="fw-medium fs-13 text-default">{{ a.label }}</div>
                    </div>
                </router-link>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="row g-3 mb-4">
            <template v-if="!d"><div v-for="i in 8" :key="i" class="col-sm-6 col-xl-3"><div class="card custom-card"><div class="card-body"><div class="skeleton" style="height:56px"></div></div></div></div></template>
            <div v-else v-for="c in cards" :key="c.label" class="col-sm-6 col-xl-3">
                <router-link :to="c.to" class="card custom-card h-100 text-decoration-none ms-kpi">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span :class="['avatar avatar-lg avatar-rounded flex-shrink-0', `bg-${c.color}-transparent`, `text-${c.color}`]"><i :class="['bi', c.icon, 'fs-4']"></i></span>
                        <div class="min-w-0">
                            <div class="text-muted fs-12">{{ c.label }}</div>
                            <div class="fs-20 fw-semibold text-default text-truncate">{{ c.value }}</div>
                            <div class="fs-11 text-muted text-truncate">{{ c.sub }}</div>
                        </div>
                    </div>
                </router-link>
            </div>
        </div>

        <div class="row g-3 mb-4" v-if="d">
            <div class="col-xl-8"><div class="card custom-card h-100"><div class="card-header"><div class="card-title">Sales – last 7 days</div></div><div class="card-body"><canvas ref="chartEl" height="110"></canvas></div></div></div>
            <div class="col-xl-4"><div class="card custom-card h-100"><div class="card-header"><div class="card-title">Top selling (30 days)</div></div>
                <ul class="list-group list-group-flush">
                    <li v-for="(t, i) in d.top" :key="t.name" class="list-group-item d-flex align-items-center gap-3">
                        <span class="avatar avatar-sm avatar-rounded bg-primary-transparent text-primary fw-semibold">{{ i + 1 }}</span>
                        <span class="flex-fill text-truncate">{{ t.name }}</span>
                        <span class="badge bg-primary-transparent">{{ t.qty }} sold</span>
                    </li>
                    <li v-if="!d.top.length" class="list-group-item text-muted text-center py-4">No sales yet</li>
                </ul></div></div>
        </div>

        <div class="row g-3" v-if="d">
            <div class="col-xl-7"><div class="card custom-card">
                <div class="card-header justify-content-between"><div class="card-title">Recent sales</div><router-link to="/sales" class="btn btn-sm btn-light">View all</router-link></div>
                <div class="table-responsive"><table class="table text-nowrap table-hover mb-0">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="s in d.recent" :key="s.id"><td class="fw-semibold">{{ s.invoice_no }}</td><td>{{ s.customer_name || 'Walk-in' }}</td><td>{{ money(s.total) }}</td>
                            <td><span class="badge text-capitalize" :class="pay(s.payment_status)">{{ s.payment_status }}</span></td>
                            <td class="text-end"><a class="btn btn-sm btn-icon btn-light" :href="`/print/sale/${s.id}`" target="_blank"><i class="bi bi-printer"></i></a></td></tr>
                        <tr v-if="!d.recent.length"><td colspan="5" class="text-center text-muted py-4">No sales yet</td></tr>
                    </tbody>
                </table></div>
            </div></div>
            <div class="col-xl-5"><div class="card custom-card">
                <div class="card-header justify-content-between"><div class="card-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Low stock</div><router-link to="/alerts/low-stock" class="btn btn-sm btn-danger-light">View all</router-link></div>
                <div class="table-responsive"><table class="table text-nowrap mb-0">
                    <thead><tr><th>Medicine</th><th>Stock</th><th>Re-order</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="m in d.low" :key="m.id"><td>{{ m.name }} <small class="text-muted">{{ m.strength }}</small></td>
                            <td><span class="badge" :class="m.total_stock <= 0 ? 'bg-danger-transparent' : 'bg-warning-transparent'">{{ m.total_stock }} units</span></td><td>{{ m.reorder_level }}</td>
                            <td class="text-end"><router-link class="btn btn-sm btn-icon btn-primary" :to="`/purchases/create?medicine=${m.id}`"><i class="bi bi-plus-lg"></i></router-link></td></tr>
                        <tr v-if="!d.low.length"><td colspan="4" class="text-center text-muted py-4">All good 🎉</td></tr>
                    </tbody>
                </table></div>
            </div></div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';
import api from '../api';
import { useAuth } from '../stores/auth';
import { money } from '../utils/format';

// Rixzo soft badge for payment status
const pay = (s) => ({ paid: 'bg-success-transparent', partial: 'bg-warning-transparent', due: 'bg-danger-transparent', unpaid: 'bg-danger-transparent' }[String(s).toLowerCase()] || 'bg-secondary-transparent');

const auth = useAuth();
const d = ref(null);
const chartEl = ref(null);
let chart;

const actions = [
    { to: '/pos', icon: 'bi-cart-plus', label: 'New Sale', color: 'primary' },
    { to: '/purchases/create', icon: 'bi-box-arrow-in-down', label: 'New Purchase', color: 'secondary' },
    { to: '/medicines?new=1', icon: 'bi-plus-square', label: 'Add Medicine', color: 'info' },
    { to: '/returns/new', icon: 'bi-arrow-counterclockwise', label: 'Customer Return', color: 'warning' },
    { to: '/reports/daily', icon: 'bi-calendar-check', label: 'Day Closing', color: 'secondary' },
];

const cards = computed(() => {
    const c = d.value.cards;
    return [
        { label: 'Today Sales', value: money(c.today_sales), sub: `${c.today_invoices} invoices`, icon: 'bi-cash-stack', color: 'primary', to: '/reports/daily' },
        { label: 'Today Profit', value: money(c.today_profit), sub: `Returns ${money(c.today_returns)}`, icon: 'bi-graph-up-arrow', color: 'success', to: '/reports/daily' },
        { label: 'This Month', value: money(c.month_sales), sub: `Purchases ${money(c.month_purchase)}`, icon: 'bi-calendar3', color: 'info', to: '/reports/summary' },
        { label: 'Stock Value', value: money(c.stock_value), sub: `${c.medicines} medicines`, icon: 'bi-boxes', color: 'secondary', to: '/reports/stock' },
        { label: 'Low Stock', value: c.low_stock, sub: 'Need re-order →', icon: 'bi-exclamation-triangle', color: 'danger', to: '/alerts/low-stock' },
        { label: 'Expiring Soon', value: c.expiring, sub: 'batches →', icon: 'bi-hourglass-split', color: 'warning', to: '/alerts/expiry' },
        { label: 'Expired', value: c.expired, sub: 'write off →', icon: 'bi-x-octagon', color: 'danger', to: '/alerts/expiry?type=expired' },
        { label: 'Customer Dues', value: money(c.customer_due), sub: `Supplier payable ${money(c.supplier_due)}`, icon: 'bi-wallet2', color: 'primary', to: '/customers?due=1' },
    ];
});

async function load() {
    const { data } = await api.get('/dashboard');
    d.value = data;
    await nextTick();
    chart?.destroy();
    if (chartEl.value) {
        chart = new Chart(chartEl.value, {
            type: 'bar',
            data: { labels: data.chart.labels, datasets: [{ data: data.chart.values, backgroundColor: 'rgba(139,126,255,.85)', hoverBackgroundColor: 'rgb(139,126,255)', borderRadius: 6, maxBarThickness: 34 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(132,146,181,.15)' } }, x: { grid: { display: false } } } },
        });
    }
}

onMounted(load);
onBeforeUnmount(() => chart?.destroy());
</script>
