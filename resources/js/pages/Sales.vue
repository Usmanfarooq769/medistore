<template>
    <div>
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Invoices</div><div class="fs-5 fw-bold">{{ totals.count || 0 }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Total sales</div><div class="fs-5 fw-bold text-success">{{ money(totals.total) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Returned</div><div class="fs-5 fw-bold text-warning">{{ money(totals.returned) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Credit / due</div><div class="fs-5 fw-bold text-danger">{{ money(totals.due) }}</div></div></div></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input v-model="filters.search" type="search" class="form-control" placeholder="Invoice, customer, phone"></div></div>
                    <div class="col-6 col-md-2"><input v-model="filters.from" type="date" class="form-control"></div>
                    <div class="col-6 col-md-2"><input v-model="filters.to" type="date" class="form-control"></div>
                    <div class="col-6 col-md-2"><select v-model="filters.method" class="form-select"><option value="">Any method</option><option value="cash">Cash</option><option value="card">Card</option><option value="online">Online</option><option value="credit">Credit</option></select></div>
                    <div class="col-6 col-md-2"><select v-model="filters.status" class="form-select"><option value="">Any status</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option></select></div>
                </div>
                <div class="chips mt-2">
                    <button v-for="r in ranges" :key="r.key" class="chip" :class="{ active: range === r.key }" @click="setRange(r.key)">{{ r.label }}</button>
                </div>
            </div>
            <DataTable ref="table" url="/sales" :filters="filters" :cols="10" @loaded="totals = $event.totals || {}">
                <template #head><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Paid</th><th>Status</th><th>Method</th><th>Cashier</th><th></th></tr></template>
                <template #row="{ item: s }">
                    <tr>
                        <td><a href="#" class="fw-semibold text-decoration-none" @click.prevent="view(s.id)">{{ s.invoice_no }}</a></td>
                        <td class="small">{{ dateTime(s.created_at) }}</td>
                        <td>{{ s.customer_name || 'Walk-in' }}<div class="small text-muted">{{ s.customer_phone }}</div></td>
                        <td><span class="badge bg-secondary">{{ s.items_count }}</span></td>
                        <td class="fw-semibold">{{ money(s.total) }}<div v-if="s.returned_amount" class="small text-warning">-{{ money(s.returned_amount) }} ret.</div></td>
                        <td>{{ money(s.paid) }}<div v-if="s.due" class="small text-danger">due {{ money(s.due) }}</div></td>
                        <td><span class="badge" :class="statusClass(s.payment_status)">{{ s.payment_status }}</span></td>
                        <td class="text-capitalize small">{{ s.payment_method }}</td><td class="small">{{ s.user }}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" @click="view(s.id)"><i class="bi bi-eye"></i></button>
                            <router-link :to="`/returns/new?invoice=${s.invoice_no}`" class="btn btn-sm btn-outline-warning ms-1" title="Return"><i class="bi bi-arrow-counterclockwise"></i></router-link>
                            <a :href="`/print/sale/${s.id}?print=1`" target="_blank" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>

        <Modal :show="!!sale" :title="sale?.invoice_no" @close="sale = null">
            <template v-if="sale">
                <div class="small text-muted mb-2">{{ dateTime(sale.created_at) }} · {{ sale.customer_name || 'Walk-in' }} · by {{ sale.user }}</div>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                    <tbody><tr v-for="i in sale.items" :key="i.id"><td>{{ i.medicine_name }}</td><td class="small">{{ i.batch_no }}</td>
                        <td class="text-end">{{ i.quantity }} <span v-if="i.returned_qty" class="badge bg-warning text-dark">-{{ i.returned_qty }}</span></td>
                        <td class="text-end">{{ i.price.toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td></tr></tbody>
                </table>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ money(sale.subtotal) }}</span></div>
                <div class="d-flex justify-content-between"><span>Discount</span><span>-{{ money(sale.discount) }}</span></div>
                <div class="d-flex justify-content-between"><span>Tax</span><span>{{ money(sale.tax) }}</span></div>
                <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span>{{ money(sale.total) }}</span></div>
                <div class="d-flex justify-content-between"><span>Paid</span><span>{{ money(sale.paid) }}</span></div>
                <div v-if="sale.due" class="d-flex justify-content-between text-danger"><span>Due</span><span>{{ money(sale.due) }}</span></div>
                <div v-if="sale.returns?.length" class="alert alert-warning small mt-2 mb-0">Returns: <span v-for="r in sale.returns" :key="r.id" class="me-2">{{ r.return_no }} ({{ money(r.total) }})</span></div>
            </template>
            <template #footer>
                <button v-if="auth.isAdmin" class="btn btn-outline-danger me-auto" @click="voidSale"><i class="bi bi-x-octagon"></i> Void</button>
                <router-link :to="`/returns/new?invoice=${sale?.invoice_no}`" class="btn btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> Return</router-link>
                <a :href="`/print/sale/${sale?.id}?print=1`" target="_blank" class="btn btn-success"><i class="bi bi-printer"></i> Print</a>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import Modal from '../components/Modal.vue';
import { useAuth } from '../stores/auth';
import { dateTime, money, shiftDate, statusClass, stockChanged, today } from '../utils/format';
import { confirm, notify } from '../utils/swal';

const auth = useAuth();
const table = ref(null);
const totals = ref({});
const sale = ref(null);
const range = ref('');
const filters = reactive({ search: '', from: '', to: '', method: '', status: '' });
const ranges = [{ key: 'today', label: 'Today' }, { key: 'yesterday', label: 'Yesterday' }, { key: '7', label: 'Last 7 days' }, { key: 'month', label: 'This month' }, { key: '', label: 'All' }];

function setRange(k) {
    range.value = k;
    const t = today();
    const map = { today: [t, t], yesterday: [shiftDate(t, -1), shiftDate(t, -1)], 7: [shiftDate(t, -6), t], month: [t.slice(0, 8) + '01', t], '': ['', ''] };
    [filters.from, filters.to] = map[k];
}

async function view(id) { sale.value = (await api.get(`/sales/${id}`)).data.data; }

async function voidSale() {
    if (!(await confirm({ title: `Void ${sale.value.invoice_no}?`, text: 'Remaining stock will be restored and due reversed.', confirmText: 'Yes, void' }))) return;
    try {
        const { data } = await api.delete(`/sales/${sale.value.id}`);
        notify(data.message);
        sale.value = null;
        table.value.reload();
        stockChanged();
    } catch (e) { /* shown */ }
}
</script>
