<template>
    <div>
        <div class="row g-2 align-items-end mb-3 no-print">
            <div class="col-auto"><label class="form-label small mb-0">From</label><input v-model="from" type="date" class="form-control"></div>
            <div class="col-auto"><label class="form-label small mb-0">To</label><input v-model="to" type="date" class="form-control"></div>
            <div class="col-auto d-flex gap-1">
                <button class="chip" @click="preset('month')">This month</button><button class="chip" @click="preset('last')">Last month</button><button class="chip" @click="preset('year')">This year</button>
            </div>
            <div class="col text-end"><button class="btn btn-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
        </div>
        <template v-if="r">
            <h5>{{ date(r.from) }} – {{ date(r.to) }}</h5>
            <div class="row g-3 mb-3">
                <div v-for="c in cards" :key="c.l" class="col-6 col-md-3"><div class="card border-start border-4" :class="'border-' + c.c"><div class="card-body py-2">
                    <div class="small text-muted">{{ c.l }}</div><div class="fw-bold fs-5">{{ c.v }}</div></div></div></div>
            </div>
            <div class="row g-3">
                <div class="col-lg-7"><div class="card h-100"><div class="card-header fw-semibold">Day-wise sales</div><div class="card-body"><canvas ref="chartEl" height="120"></canvas></div></div></div>
                <div class="col-lg-5"><div class="card h-100"><div class="card-header fw-semibold">Top 15 medicines</div>
                    <div class="table-responsive"><table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Medicine</th><th class="text-end">Qty</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr></thead>
                        <tbody>
                            <tr v-for="m in r.top" :key="m.medicine_name"><td>{{ m.medicine_name }}</td><td class="text-end">{{ m.qty }}</td><td class="text-end">{{ Number(m.revenue).toFixed(2) }}</td><td class="text-end text-success">{{ Number(m.profit).toFixed(2) }}</td></tr>
                            <tr v-if="!r.top.length"><td colspan="4" class="text-center text-muted py-3">No sales</td></tr>
                        </tbody>
                    </table></div></div></div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import api from '../../api';
import { date, money, today } from '../../utils/format';

const from = ref(today().slice(0, 8) + '01');
const to = ref(today());
const r = ref(null);
const chartEl = ref(null);
let chart;

function preset(k) {
    const d = new Date();
    const iso = (x) => new Date(x.getTime() - x.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    if (k === 'month') { from.value = today().slice(0, 8) + '01'; to.value = today(); }
    if (k === 'last') { from.value = iso(new Date(d.getFullYear(), d.getMonth() - 1, 1)); to.value = iso(new Date(d.getFullYear(), d.getMonth(), 0)); }
    if (k === 'year') { from.value = `${d.getFullYear()}-01-01`; to.value = today(); }
}

const cards = computed(() => {
    const x = r.value;
    return [
        { l: 'Invoices', v: x.sales.invoices, c: 'primary' }, { l: 'Net sales', v: money(x.sales.net), c: 'success' },
        { l: 'Customer returns', v: money(x.returns.customer), c: 'warning' }, { l: 'Cost of goods sold', v: money(x.cogs.cost), c: 'secondary' },
        { l: 'Gross profit', v: money(x.gross_profit), c: 'info' }, { l: 'Losses (expired/damaged/returns)', v: money(x.losses.adjustments + x.losses.returns), c: 'danger' },
        { l: 'Net profit (approx)', v: money(x.net_profit), c: 'dark' }, { l: 'Purchases', v: `${money(x.purchases.total)} · ret ${money(x.returns.supplier)}`, c: 'primary' },
    ];
});

watch([from, to], async () => {
    r.value = (await api.get('/reports/summary', { params: { from: from.value, to: to.value } })).data;
    await nextTick();
    chart?.destroy();
    if (chartEl.value) {
        chart = new Chart(chartEl.value, {
            type: 'line',
            data: { labels: r.value.daily.map((d) => date(d.sale_date)), datasets: [{ data: r.value.daily.map((d) => Number(d.total)), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.15)', fill: true, tension: 0.3 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }
}, { immediate: true });
onBeforeUnmount(() => chart?.destroy());
</script>
