<template>
    <div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><router-link to="/returns/new" class="card stat-card h-100 text-center justify-content-center py-3 text-warning">
                <i class="bi bi-arrow-counterclockwise fs-2"></i><span class="fw-semibold">New customer return</span></router-link></div>
            <div class="col-4 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Returns</div><div class="fs-5 fw-bold">{{ totals.count || 0 }}</div></div></div></div>
            <div class="col-4 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Return value</div><div class="fs-5 fw-bold text-warning">{{ money(totals.total) }}</div></div></div></div>
            <div class="col-4 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Refunded</div><div class="fs-5 fw-bold text-danger">{{ money(totals.refunded) }}</div></div></div></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input v-model="filters.search" type="search" class="form-control" placeholder="Return no / invoice no / customer"></div></div>
                    <div class="col-6 col-md-2"><input v-model="filters.from" type="date" class="form-control"></div>
                    <div class="col-6 col-md-2"><input v-model="filters.to" type="date" class="form-control"></div>
                    <div class="col-md-2"><select v-model="filters.type" class="form-select"><option value="">All returns</option><option value="invoice">With invoice</option><option value="no_invoice">Without invoice</option></select></div>
                </div>
            </div>
            <DataTable url="/sale-returns" :filters="filters" :cols="9" @loaded="totals = $event.totals || {}">
                <template #head><tr><th>Return no</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Items</th><th>Value</th><th>Refund</th><th>By</th><th></th></tr></template>
                <template #row="{ item: r }">
                    <tr>
                        <td><a href="#" class="fw-semibold text-decoration-none" @click.prevent="view(r.id)">{{ r.return_no }}</a></td>
                        <td>{{ date(r.return_date) }}</td>
                        <td><span v-if="r.invoice_no">{{ r.invoice_no }}</span><span v-else class="badge bg-secondary">No invoice</span></td>
                        <td>{{ r.customer_name || 'Walk-in' }}</td>
                        <td><span class="badge bg-secondary">{{ r.items_count }}</span></td>
                        <td class="fw-semibold">{{ money(r.total) }}<div v-if="r.deduction" class="small text-muted">-{{ money(r.deduction) }} fee</div></td>
                        <td>{{ money(r.refund_amount) }} <span class="small text-muted text-capitalize">({{ r.refund_method }})</span>
                            <div v-if="r.due_adjusted" class="small text-primary">{{ money(r.due_adjusted) }} due adj.</div></td>
                        <td class="small">{{ r.user }}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" @click="view(r.id)"><i class="bi bi-eye"></i></button>
                            <a :href="`/print/return/${r.id}?print=1`" target="_blank" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>

        <Modal :show="!!ret" :title="ret?.return_no" size="lg" @close="ret = null">
            <template v-if="ret">
                <div class="small text-muted mb-2">{{ date(ret.return_date) }} · {{ ret.customer_name || 'Walk-in' }} · Invoice {{ ret.invoice_no || '—' }} · by {{ ret.user }}</div>
                <table class="table table-sm">
                    <thead><tr><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th><th>Condition</th><th>Stock</th></tr></thead>
                    <tbody>
                        <tr v-for="i in ret.items" :key="i.id">
                            <td>{{ i.medicine_name }}</td><td class="small">{{ i.batch_no }}</td><td class="text-end">{{ i.quantity }}</td>
                            <td class="text-end">{{ i.price.toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td>
                            <td><span class="badge text-capitalize" :class="conditionClass(i.condition)">{{ i.condition }}</span></td>
                            <td><span v-if="i.restocked" class="text-success small"><i class="bi bi-check-circle"></i> Restocked</span><span v-else class="text-danger small"><i class="bi bi-x-circle"></i> Not restocked</span></td>
                        </tr>
                    </tbody>
                </table>
                <div class="d-flex justify-content-between"><span>Items value</span><span>{{ money(ret.subtotal) }}</span></div>
                <div class="d-flex justify-content-between"><span>Deduction</span><span>-{{ money(ret.deduction) }}</span></div>
                <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>{{ money(ret.total) }}</span></div>
                <div v-if="ret.due_adjusted" class="d-flex justify-content-between text-primary"><span>Adjusted against due</span><span>{{ money(ret.due_adjusted) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-capitalize">Refunded ({{ ret.refund_method }})</span><span>{{ money(ret.refund_amount) }}</span></div>
                <div v-if="ret.reason" class="small text-muted mt-2">Reason: {{ ret.reason }}</div>
            </template>
            <template #footer><a :href="`/print/return/${ret?.id}?print=1`" target="_blank" class="btn btn-success"><i class="bi bi-printer"></i> Print credit note</a></template>
        </Modal>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import api from '../../api';
import DataTable from '../../components/DataTable.vue';
import Modal from '../../components/Modal.vue';
import { conditionClass, date, money } from '../../utils/format';

const filters = reactive({ search: '', from: '', to: '', type: '' });
const totals = ref({});
const ret = ref(null);
async function view(id) { ret.value = (await api.get(`/sale-returns/${id}`)).data.data; }
</script>
