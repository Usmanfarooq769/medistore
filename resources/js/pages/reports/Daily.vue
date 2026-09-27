<template>
    <div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print">
            <button class="btn btn-outline-secondary" @click="day = shiftDate(day, -1)"><i class="bi bi-chevron-left"></i></button>
            <input v-model="day" type="date" :max="today()" class="form-control w-auto">
            <button class="btn btn-outline-secondary" :disabled="day >= today()" @click="day = shiftDate(day, 1)"><i class="bi bi-chevron-right"></i></button>
            <button class="btn btn-light" @click="day = today()">Today</button>
            <button class="btn btn-success ms-auto" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <template v-if="r">
            <h5 class="mb-3">{{ auth.settings.store_name }} — {{ date(r.date) }}</h5>
            <div class="row g-3 mb-3">
                <div v-for="c in cards" :key="c.l" class="col-6 col-md-3"><div class="card border-start border-4" :class="'border-' + c.c"><div class="card-body py-2">
                    <div class="small text-muted">{{ c.l }}</div><div class="fw-bold fs-5">{{ c.v }}</div></div></div></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-5"><div class="card h-100">
                    <div class="card-header fw-semibold">Cash summary</div>
                    <table class="table table-sm mb-0">
                        <tr v-for="(m, k) in r.by_method" :key="k"><td class="text-capitalize">{{ k }} sales ({{ m.count }})</td><td class="text-end">{{ money(m.total) }}</td></tr>
                        <tr><td>+ Dues received (cash)</td><td class="text-end">{{ money(r.summary.customer_received) }}</td></tr>
                        <tr><td>+ Supplier cash back (returns)</td><td class="text-end">{{ money(r.summary.supplier_cash_back) }}</td></tr>
                        <tr><td>− Cash refunds (customer returns)</td><td class="text-end">{{ money(r.summary.cash_refunds) }}</td></tr>
                        <tr><td>− Paid to suppliers (cash)</td><td class="text-end">{{ money(r.summary.supplier_paid) }}</td></tr>
                        <tr class="table-success fw-bold"><td>Cash in hand</td><td class="text-end">{{ money(r.summary.cash_in_hand) }}</td></tr>
                        <tr><td class="text-muted">Purchases today</td><td class="text-end text-muted">{{ money(r.summary.purchases) }}</td></tr>
                    </table>
                </div></div>
                <div class="col-md-7"><div class="card h-100">
                    <div class="card-header fw-semibold">Medicines sold (net of returns)</div>
                    <div class="table-responsive" style="max-height:320px"><table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Medicine</th><th class="text-end">Qty</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr></thead>
                        <tbody>
                            <tr v-for="i in r.items" :key="i.medicine_name"><td>{{ i.medicine_name }}</td><td class="text-end">{{ i.qty }}</td><td class="text-end">{{ Number(i.revenue).toFixed(2) }}</td><td class="text-end text-success">{{ (i.revenue - i.cost).toFixed(2) }}</td></tr>
                            <tr v-if="!r.items.length"><td colspan="4" class="text-center text-muted py-3">Nothing sold</td></tr>
                        </tbody>
                    </table></div>
                </div></div>
            </div>
            <div class="row g-3">
                <div class="col-md-8"><div class="card">
                    <div class="card-header fw-semibold">Invoices</div>
                    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Invoice</th><th>Time</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Due</th><th>Method</th><th>Cashier</th></tr></thead>
                        <tbody>
                            <tr v-for="s in r.sales" :key="s.id"><td><a :href="`/print/sale/${s.id}`" target="_blank">{{ s.invoice_no }}</a></td><td>{{ s.time }}</td><td>{{ s.customer_name || 'Walk-in' }}</td>
                                <td class="text-end">{{ s.total.toFixed(2) }}</td><td class="text-end" :class="{ 'text-danger': s.due }">{{ s.due.toFixed(2) }}</td><td class="text-capitalize">{{ s.payment_method }}</td><td>{{ s.user }}</td></tr>
                            <tr v-if="!r.sales.length"><td colspan="7" class="text-center text-muted py-3">No invoices</td></tr>
                        </tbody>
                    </table></div>
                </div></div>
                <div class="col-md-4"><div class="card">
                    <div class="card-header fw-semibold">Customer returns</div>
                    <ul class="list-group list-group-flush">
                        <li v-for="x in r.returns" :key="x.id" class="list-group-item d-flex justify-content-between"><a :href="`/print/return/${x.id}`" target="_blank">{{ x.return_no }}</a><span>{{ money(x.total) }} <small class="text-muted text-capitalize">{{ x.refund_method }}</small></span></li>
                        <li v-if="!r.returns.length" class="list-group-item text-muted">No returns</li>
                    </ul>
                </div></div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../api';
import { useAuth } from '../../stores/auth';
import { date, money, shiftDate, today } from '../../utils/format';

const auth = useAuth();
const day = ref(today());
const r = ref(null);

const cards = computed(() => {
    const s = r.value.summary;
    return [
        { l: 'Invoices', v: s.invoices, c: 'primary' }, { l: 'Gross sales', v: money(s.gross), c: 'secondary' },
        { l: 'Discount', v: money(s.discount), c: 'danger' }, { l: 'Net sales', v: money(s.net), c: 'success' },
        { l: `Returns (${s.returns_count})`, v: money(s.returns), c: 'warning' }, { l: 'Credit given', v: money(s.credit), c: 'danger' },
        { l: 'Est. profit', v: money(s.profit), c: 'info' }, { l: 'Cash in hand', v: money(s.cash_in_hand), c: 'dark' },
    ];
});

watch(day, async (d) => { r.value = (await api.get('/reports/daily', { params: { date: d } })).data; }, { immediate: true });
</script>
