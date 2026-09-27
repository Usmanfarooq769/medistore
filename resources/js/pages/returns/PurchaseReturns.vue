<template>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-md-3"><input v-model="filters.search" type="search" class="form-control" placeholder="Return no"></div>
                <div class="col-md-3"><select v-model="filters.supplier_id" class="form-select"><option value="">All suppliers</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                <div class="col-6 col-md-2"><input v-model="filters.from" type="date" class="form-control"></div>
                <div class="col-6 col-md-2"><input v-model="filters.to" type="date" class="form-control"></div>
                <div class="col-md-2 text-md-end"><router-link to="/supplier-returns/new" class="btn btn-danger"><i class="bi bi-plus-lg"></i> New</router-link></div>
            </div>
        </div>
        <DataTable url="/purchase-returns" :filters="filters" :cols="8">
            <template #head><tr><th>Return no</th><th>Date</th><th>Supplier</th><th>Items</th><th>Total</th><th>Settlement</th><th>By</th><th></th></tr></template>
            <template #row="{ item: r }">
                <tr>
                    <td class="fw-semibold">{{ r.return_no }}</td><td>{{ date(r.return_date) }}</td><td>{{ r.supplier }}</td>
                    <td><span class="badge bg-secondary">{{ r.items_count }}</span></td><td class="fw-semibold">{{ money(r.total) }}</td>
                    <td><span class="badge" :class="r.refund_type === 'cash' ? 'bg-success' : 'bg-info text-dark'">{{ r.refund_type === 'cash' ? 'Cash back' : 'Payable reduced' }}</span></td>
                    <td class="small">{{ r.user }}</td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-secondary" @click="view(r.id)"><i class="bi bi-eye"></i></button></td>
                </tr>
            </template>
        </DataTable>
        <Modal :show="!!ret" :title="ret?.return_no" size="lg" @close="ret = null">
            <template v-if="ret">
                <div class="small text-muted mb-2">{{ date(ret.return_date) }} · {{ ret.supplier }} · {{ ret.reason }}</div>
                <table class="table table-sm">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th class="text-end">Total</th></tr></thead>
                    <tbody><tr v-for="i in ret.items" :key="i.id"><td>{{ i.medicine }}</td><td>{{ i.batch_no }}</td><td>{{ date(i.expiry_date) }}</td>
                        <td class="text-end">{{ i.quantity }}</td><td class="text-end">{{ i.price.toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td></tr></tbody>
                </table>
                <div class="text-end fw-bold">Total {{ money(ret.total) }}</div>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api';
import DataTable from '../../components/DataTable.vue';
import Modal from '../../components/Modal.vue';
import { date, money } from '../../utils/format';

const suppliers = ref([]);
const filters = reactive({ search: '', supplier_id: '', from: '', to: '' });
const ret = ref(null);
async function view(id) { ret.value = (await api.get(`/purchase-returns/${id}`)).data.data; }
onMounted(async () => { suppliers.value = (await api.get('/lookups')).data.suppliers; });
</script>
