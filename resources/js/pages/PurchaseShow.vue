<template>
    <div v-if="p">
        <div class="d-flex justify-content-between mb-3 no-print">
            <router-link to="/purchases" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</router-link>
            <div class="d-flex gap-2">
                <button v-if="p.supplier.balance > 0" class="btn btn-warning" @click="payOpen = true"><i class="bi bi-cash"></i> Pay supplier</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                <button class="btn btn-outline-danger" @click="voidIt"><i class="bi bi-x-octagon"></i> Void</button>
            </div>
        </div>
        <div class="card"><div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1">{{ p.reference_no }}</h5>
                    <div class="text-muted small">Date {{ date(p.purchase_date) }} · Supplier bill {{ p.supplier_invoice_no || '—' }}</div>
                    <div class="text-muted small">Entered by {{ p.user || '—' }} on {{ dateTime(p.created_at) }}</div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="fw-semibold">{{ p.supplier.name }}</div>
                    <div class="small text-muted">{{ p.supplier.phone }} · {{ p.supplier.address }}</div>
                    <span class="badge text-uppercase mt-1" :class="statusClass(p.payment_status)">{{ p.payment_status }}</span>
                </div>
            </div>
            <div class="table-responsive"><table class="table table-bordered table-sm">
                <thead class="table-light"><tr><th>#</th><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Bonus</th><th class="text-end">Cost</th><th class="text-end">Sale</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    <tr v-for="(it, i) in p.items" :key="it.id"><td>{{ i + 1 }}</td><td>{{ it.medicine }}</td><td>{{ it.batch_no }}</td><td>{{ date(it.expiry_date) }}</td>
                        <td class="text-end">{{ it.quantity }}</td><td class="text-end">{{ it.bonus_qty || '—' }}</td><td class="text-end">{{ it.purchase_price.toFixed(2) }}</td>
                        <td class="text-end">{{ it.sale_price.toFixed(2) }}</td><td class="text-end">{{ it.total.toFixed(2) }}</td></tr>
                </tbody>
                <tfoot>
                    <tr><th colspan="8" class="text-end">Subtotal</th><th class="text-end">{{ p.subtotal.toFixed(2) }}</th></tr>
                    <tr><th colspan="8" class="text-end">Discount</th><th class="text-end">-{{ p.discount.toFixed(2) }}</th></tr>
                    <tr><th colspan="8" class="text-end">Tax / charges</th><th class="text-end">{{ p.tax.toFixed(2) }}</th></tr>
                    <tr class="table-success"><th colspan="8" class="text-end">Total</th><th class="text-end">{{ money(p.total) }}</th></tr>
                    <tr><th colspan="8" class="text-end">Paid</th><th class="text-end">{{ money(p.paid) }}</th></tr>
                    <tr><th colspan="8" class="text-end text-danger">Due</th><th class="text-end text-danger">{{ money(p.due) }}</th></tr>
                </tfoot>
            </table></div>
            <p v-if="p.note" class="small text-muted mb-0">Note: {{ p.note }}</p>
        </div></div>
        <PaymentModal :show="payOpen" title="Pay supplier" label="Payable to" :name="p.supplier.name" :due="p.supplier.balance" :endpoint="`/suppliers/${p.supplier_id}/payments`" @close="payOpen = false" @saved="load" />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import PaymentModal from '../components/PaymentModal.vue';
import { date, dateTime, money, statusClass, stockChanged } from '../utils/format';
import { confirm, notify } from '../utils/swal';

const route = useRoute();
const router = useRouter();
const p = ref(null);
const payOpen = ref(false);

async function load() { p.value = (await api.get(`/purchases/${route.params.id}`)).data.data; }

async function voidIt() {
    if (!(await confirm({ title: 'Void this purchase?', text: 'All its batches will be removed from stock.', confirmText: 'Yes, void' }))) return;
    try {
        const { data } = await api.delete(`/purchases/${p.value.id}`);
        notify(data.message);
        stockChanged();
        router.push('/purchases');
    } catch (e) { /* shown */ }
}
onMounted(load);
</script>
