<template>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input v-model="filters.search" type="search" class="form-control" placeholder="Ref / supplier bill no"></div></div>
                <div class="col-md-2"><select v-model="filters.supplier_id" class="form-select"><option value="">All suppliers</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                <div class="col-md-2"><select v-model="filters.status" class="form-select"><option value="">Any payment</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option></select></div>
                <div class="col-6 col-md-2"><input v-model="filters.from" type="date" class="form-control"></div>
                <div class="col-6 col-md-1"><input v-model="filters.to" type="date" class="form-control"></div>
                <div class="col-md-2 text-md-end"><router-link to="/purchases/create" class="btn btn-success"><i class="bi bi-plus-lg"></i> New</router-link></div>
            </div>
        </div>
        <DataTable url="/purchases" :filters="filters" :cols="10">
            <template #head><tr><th>Reference</th><th>Date</th><th>Supplier</th><th>Bill no</th><th>Items</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th></th></tr></template>
            <template #row="{ item: p }">
                <tr>
                    <td><router-link :to="`/purchases/${p.id}`" class="fw-semibold text-decoration-none">{{ p.reference_no }}</router-link></td>
                    <td>{{ date(p.purchase_date) }}</td><td>{{ p.supplier?.name }}</td><td>{{ p.supplier_invoice_no || '—' }}</td>
                    <td><span class="badge bg-secondary">{{ p.items_count }}</span></td><td class="fw-semibold">{{ money(p.total) }}</td><td>{{ money(p.paid) }}</td>
                    <td :class="{ 'text-danger fw-semibold': p.due > 0 }">{{ money(p.due) }}</td>
                    <td><span class="badge" :class="statusClass(p.payment_status)">{{ p.payment_status }}</span></td>
                    <td class="text-end"><router-link :to="`/purchases/${p.id}`" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></router-link></td>
                </tr>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import { date, money, statusClass } from '../utils/format';

const suppliers = ref([]);
const filters = reactive({ search: '', supplier_id: '', status: '', from: '', to: '' });
onMounted(async () => { suppliers.value = (await api.get('/lookups')).data.suppliers; });
</script>
