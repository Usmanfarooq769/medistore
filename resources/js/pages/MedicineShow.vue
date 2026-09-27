<template>
    <div v-if="m">
        <div class="row g-3 mb-3">
            <div class="col-lg-4"><div class="card h-100"><div class="card-body">
                <h5 class="fw-bold mb-0">{{ m.display_name }}</h5>
                <div class="text-muted small mb-2">{{ m.generic_name }}</div>
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted fw-normal">Company</th><td>{{ m.manufacturer || '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Category</th><td>{{ m.category || '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Barcode</th><td>{{ m.barcode || '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Price</th><td>{{ money(m.sale_price) }} <small class="text-muted">(cost {{ money(m.purchase_price) }})</small></td></tr>
                    <tr><th class="text-muted fw-normal">Rack</th><td>{{ m.rack || '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Total stock</th><td class="fw-bold" :class="m.is_low ? 'text-danger' : 'text-success'">{{ m.total_stock }} {{ m.unit }} <small class="text-muted fw-normal">(re-order at {{ m.reorder_level }})</small></td></tr>
                </table>
                <div class="d-flex gap-2 mt-3">
                    <router-link :to="`/purchases/create?medicine=${m.id}`" class="btn btn-success btn-sm"><i class="bi bi-box-arrow-in-down"></i> Purchase</router-link>
                    <router-link :to="`/adjustments?medicine=${m.id}`" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders"></i> Adjust</router-link>
                </div>
            </div></div></div>
            <div class="col-lg-8"><div class="card h-100">
                <div class="card-header fw-semibold">Batches in stock (sold first-expiry-first-out)</div>
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Batch</th><th>Expiry</th><th>Supplier</th><th>Cost</th><th>Sale</th><th>Qty left</th></tr></thead>
                    <tbody>
                        <tr v-for="b in batches" :key="b.id" :class="{ 'table-danger': b.is_expired }">
                            <td class="fw-semibold">{{ b.batch_no || '—' }}</td>
                            <td>{{ date(b.expiry_date) }} <span v-if="b.is_expired" class="badge bg-danger">Expired</span><span v-else-if="b.days_left !== null && b.days_left <= 60" class="badge bg-warning text-dark">{{ b.days_left }}d</span></td>
                            <td class="small">{{ b.supplier || '—' }}</td><td>{{ money(b.purchase_price) }}</td><td>{{ money(b.sale_price) }}</td>
                            <td class="fw-bold">{{ b.quantity }} / <span class="text-muted fw-normal">{{ b.initial_quantity }}</span></td>
                        </tr>
                        <tr v-if="!batches.length"><td colspan="6" class="text-center text-muted py-4">No stock. <router-link :to="`/purchases/create?medicine=${m.id}`">Purchase now</router-link></td></tr>
                    </tbody>
                </table></div>
            </div></div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Stock movements (every IN / OUT)</div>
            <DataTable :url="`/medicines/${m.id}/movements`" :cols="6" table-class="table-sm">
                <template #head><tr><th>Date</th><th>Type</th><th>Reference</th><th>Note</th><th class="text-end">In / Out</th><th class="text-end">Balance</th></tr></template>
                <template #row="{ item }">
                    <tr>
                        <td class="small">{{ dateTime(item.created_at) }}</td>
                        <td><span class="badge text-capitalize" :class="typeClass[item.type] || 'bg-secondary'">{{ item.type.replace('_', ' ') }}</span></td>
                        <td>{{ item.reference }}</td><td class="small text-muted">{{ item.note }}</td>
                        <td class="text-end fw-semibold" :class="item.quantity > 0 ? 'text-success' : 'text-danger'">{{ item.quantity > 0 ? '+' : '' }}{{ item.quantity }}</td>
                        <td class="text-end">{{ item.balance_after }}</td>
                    </tr>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import { date, dateTime, money } from '../utils/format';

const route = useRoute();
const m = ref(null);
const batches = ref([]);
const typeClass = {
    purchase: 'bg-primary', sale: 'bg-success', sale_return: 'bg-info text-dark', sale_void: 'bg-secondary', purchase_void: 'bg-dark',
    purchase_return: 'bg-warning text-dark', adjustment_in: 'bg-warning text-dark', adjustment_out: 'bg-danger',
};

onMounted(async () => {
    const [a, b] = await Promise.all([api.get(`/medicines/${route.params.id}`), api.get(`/medicines/${route.params.id}/batches`)]);
    m.value = a.data.data;
    batches.value = b.data.data;
});
</script>
