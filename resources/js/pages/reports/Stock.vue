<template>
    <div>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Units in stock</div><div class="fs-4 fw-bold">{{ num(totals.units) }}</div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Stock value (cost)</div><div class="fs-4 fw-bold text-primary">{{ money(totals.cost_value) }}</div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Stock value (sale)</div><div class="fs-4 fw-bold text-success">{{ money(totals.sale_value) }}</div>
                <div class="small text-muted">Potential profit {{ money(totals.sale_value - totals.cost_value) }}</div></div></div></div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div class="input-group" style="max-width:340px"><span class="input-group-text"><i class="bi bi-search"></i></span><input v-model="filters.search" type="search" class="form-control" placeholder="Search medicine…"></div>
                <button class="btn btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
            <DataTable url="/reports/stock" :filters="filters" :cols="6" :per-page="25" table-class="table-sm" @loaded="totals = $event.totals || {}">
                <template #head><tr><th>Medicine</th><th>Category</th><th>Company</th><th class="text-end">Stock</th><th class="text-end">Value (cost)</th><th class="text-end">Value (sale)</th></tr></template>
                <template #row="{ item: m }">
                    <tr>
                        <td><router-link :to="`/medicines/${m.id}`" class="fw-semibold text-decoration-none">{{ m.display_name }}</router-link></td>
                        <td>{{ m.category || '—' }}</td><td>{{ m.manufacturer || '—' }}</td>
                        <td class="text-end" :class="{ 'text-danger fw-semibold': m.is_low }">{{ m.total_stock }} {{ m.unit }}</td>
                        <td class="text-end">{{ money(m.cost_value) }}</td><td class="text-end">{{ money(m.sale_value) }}</td>
                    </tr>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import DataTable from '../../components/DataTable.vue';
import { money, num } from '../../utils/format';

const filters = reactive({ search: '' });
const totals = ref({});
</script>
