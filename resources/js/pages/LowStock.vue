<template>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="input-group" style="max-width:340px"><span class="input-group-text"><i class="bi bi-search"></i></span><input v-model="filters.search" type="search" class="form-control" placeholder="Search medicine…"></div>
            <button class="btn btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print re-order list</button>
        </div>
        <DataTable url="/alerts/low-stock" :filters="filters" :cols="7" :per-page="25" empty-text="🎉 No low stock medicines">
            <template #head><tr><th>Medicine</th><th>Company</th><th>Stock</th><th>Re-order level</th><th>Suggested order</th><th>Est. cost</th><th class="no-print"></th></tr></template>
            <template #row="{ item: m }">
                <tr>
                    <td class="fw-semibold">{{ m.display_name }}</td><td>{{ m.manufacturer || '—' }}</td>
                    <td><span class="badge fs-6" :class="m.total_stock <= 0 ? 'bg-danger' : 'bg-warning text-dark'">{{ m.total_stock }}</span> <small>{{ m.unit }}</small></td>
                    <td>{{ m.reorder_level }}</td>
                    <td class="fw-semibold">{{ suggest(m) }} {{ m.unit }}</td><td>{{ money(suggest(m) * m.purchase_price) }}</td>
                    <td class="text-end no-print"><router-link v-if="auth.isAdmin" :to="`/purchases/create?medicine=${m.id}`" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-down"></i> Purchase</router-link></td>
                </tr>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import DataTable from '../components/DataTable.vue';
import { useAuth } from '../stores/auth';
import { money } from '../utils/format';

const auth = useAuth();
const filters = reactive({ search: '' });
const suggest = (m) => Math.max(m.reorder_level * 2 - m.total_stock, 1);
</script>
