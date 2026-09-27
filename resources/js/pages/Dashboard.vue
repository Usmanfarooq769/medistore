<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h4 class="fw-bold mb-0">Hello, {{ auth.user?.name }} 👋</h4>
                <small class="text-muted">Flow: <b>Supplier → Purchase (stock in) → POS sale → Receipt → Return → Reports</b></small>
            </div>
            <button class="btn btn-sm btn-light" @click="load"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>

        <div class="row g-2 mb-3">
            <div v-for="a in actions" :key="a.to" class="col-6 col-md">
                <router-link :to="a.to" class="card stat-card h-100 text-center py-3">
                    <i :class="['bi', a.icon, 'fs-3', 'text-' + a.color]"></i><span class="fw-semibold small mt-1">{{ a.label }}</span>
                </router-link>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <template v-if="!d"><div v-for="i in 8" :key="i" class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="skeleton" style="height:48px"></div></div></div></div></template>
            <div v-else v-for="c in cards" :key="c.label" class="col-6 col-xl-3">
                <router-link :to="c.to" class="card stat-card h-100">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div :class="['icon', `bg-${c.color}`, 'bg-opacity-10', `text-${c.color}`]"><i :class="['bi', c.icon]"></i></div>
                        <div class="min-w-0"><div class="small text-muted">{{ c.label }}</div><div class="fs-5 fw-bold text-truncate">{{ c.value }}</div><div class="small text-muted text-truncate">{{ c.sub }}</div></div>
                    </div>
                </router-link>
            </div>
        </div>

        <div class="row g-3 mb-3" v-if="d">
            <div class="col-lg-8"><div class="card h-100"><div class="card-header fw-semibold">Sales – last 7 days</div><div class="card-body"><canvas ref="chartEl" height="110"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card h-100"><div class="card-header fw-semibold">Top selling (30 days)</div>
                <ul class="list-group list-group-flush">
                    <li v-for="t in d.top" :key="t.name" class="list-group-item d-flex justify-content-between"><span>{{ t.name }}</span><span class="badge bg-success-subtle text-success">{{ t.qty }} sold</span></li>
                    <li v-if="!d.top.length" class="list-group-item text-muted">No sales yet</li>
                </ul></div></div>
        </div>

        <div class="row g-3" v-if="d">
            <div class="col-lg-7"><div class="card">
                <div class="card-header d-flex justify-content-between"><span class="fw-semibold">Recent sales</span><router-link to="/sales" class="btn btn-sm btn-outline-secondary">All</router-link></div>
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="s in d.recent" :key="s.id"><td class="fw-semibold">{{ s.invoice_no }}</td><td>{{ s.customer_name || 'Walk-in' }}</td><td>{{ money(s.total) }}</td>
                            <td><span class="badge" :class="statusClass(s.payment_status)">{{ s.payment_status }}</span></td>
                            <td><a class="btn btn-sm btn-light" :href="`/print/sale/${s.id}`" target="_blank"><i class="bi bi-printer"></i></a></td></tr>
                        <tr v-if="!d.recent.length"><td colspan="5" class="text-center text-muted py-4">No sales yet</td></tr>
                    </tbody>
                </table></div>
            </div></div>
            <div class="col-lg-5"><div class="card">
                <div class="card-header d-flex justify-content-between"><span class="fw-semibold text-danger"><i class="bi bi-exclamation-triangle"></i> Low stock</span><router-link to="/alerts/low-stock" class="btn btn-sm btn-outline-danger">All</router-link></div>
                <div class="table-responsive"><table class="table mb-0">
                    <thead class="table-light"><tr><th>Medicine</th><th>Stock</th><th>Re-order</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="m in d.low" :key="m.id"><td>{{ m.name }} <small class="text-muted">{{ m.strength }}</small></td>
                            <td><span class="badge" :class="m.total_stock <= 0 ? 'bg-danger' : 'bg-warning text-dark'">{{ m.total_stock }} {{ m.unit }}</span></td><td>{{ m.reorder_level }}</td>
                            <td><router-link class="btn btn-sm btn-success" :to="`/purchases/create?medicine=${m.id}`"><i class="bi bi-plus"></i></router-link></td></tr>
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
import { money, statusClass } from '../utils/format';

const auth = useAuth();
const d = ref(null);
const chartEl = ref(null);
let chart;

const actions = [
    { to: '/pos', icon: 'bi-cart-plus', label: 'New Sale', color: 'success' },
    { to: '/purchases/create', icon: 'bi-box-arrow-in-down', label: 'New Purchase', color: 'primary' },
    { to: '/medicines?new=1', icon: 'bi-plus-square', label: 'Add Medicine', color: 'info' },
    { to: '/returns/new', icon: 'bi-arrow-counterclockwise', label: 'Customer Return', color: 'warning' },
    { to: '/reports/daily', icon: 'bi-calendar-check', label: 'Day Closing', color: 'secondary' },
];

const cards = computed(() => {
    const c = d.value.cards;
    return [
        { label: 'Today Sales', value: money(c.today_sales), sub: `${c.today_invoices} invoices`, icon: 'bi-cash-stack', color: 'success', to: '/reports/daily' },
        { label: 'Today Profit', value: money(c.today_profit), sub: `Returns ${money(c.today_returns)}`, icon: 'bi-graph-up-arrow', color: 'primary', to: '/reports/daily' },
        { label: 'This Month', value: money(c.month_sales), sub: `Purchases ${money(c.month_purchase)}`, icon: 'bi-calendar3', color: 'info', to: '/reports/summary' },
        { label: 'Stock Value', value: money(c.stock_value), sub: `${c.medicines} medicines`, icon: 'bi-boxes', color: 'secondary', to: '/reports/stock' },
        { label: 'Low Stock', value: c.low_stock, sub: 'Need re-order →', icon: 'bi-exclamation-triangle', color: 'danger', to: '/alerts/low-stock' },
        { label: 'Expiring Soon', value: c.expiring, sub: 'batches →', icon: 'bi-hourglass-split', color: 'warning', to: '/alerts/expiry' },
        { label: 'Expired', value: c.expired, sub: 'write off →', icon: 'bi-x-octagon', color: 'danger', to: '/alerts/expiry?type=expired' },
        { label: 'Customer Dues', value: money(c.customer_due), sub: `Supplier payable ${money(c.supplier_due)}`, icon: 'bi-wallet2', color: 'dark', to: '/customers?due=1' },
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
            data: { labels: data.chart.labels, datasets: [{ data: data.chart.values, backgroundColor: 'rgba(25,135,84,.75)', borderRadius: 6 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }
}

onMounted(load);
onBeforeUnmount(() => chart?.destroy());
</script>
