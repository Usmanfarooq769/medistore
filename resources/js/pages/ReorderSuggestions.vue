<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div><h4 class="fw-bold mb-1">Smart Reorder Suggestions</h4><p class="text-muted small mb-0">Demand-based recommendations for the active branch, excluding expired stock.</p></div>
            <button class="btn btn-outline-secondary btn-sm" @click="load"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
        <div class="card mb-3"><div class="card-body"><div class="row g-2 align-items-end">
            <div class="col-4 col-md-3"><label class="form-label small">Sales history (days)</label><input v-model.number="filters.history_days" type="number" min="7" max="90" class="form-control form-control-sm"></div>
            <div class="col-4 col-md-3"><label class="form-label small">Supplier lead time (days)</label><input v-model.number="filters.lead_days" type="number" min="1" max="60" class="form-control form-control-sm"></div>
            <div class="col-4 col-md-3"><label class="form-label small">Target stock cover (days)</label><input v-model.number="filters.target_days" type="number" min="7" max="120" class="form-control form-control-sm"></div>
            <div class="col-md-3"><button class="btn btn-success btn-sm w-100" @click="load">Calculate suggestions</button></div>
        </div></div></div>
        <div class="row g-2 mb-3"><div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Medicines to reorder</div><div class="fs-4 fw-bold">{{ rows.length }}</div></div></div></div><div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Suggested total units</div><div class="fs-4 fw-bold">{{ rows.reduce((n,r)=>n+r.suggested_quantity,0).toLocaleString() }}</div></div></div></div></div>
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Medicine</th><th>Stock</th><th>Incoming</th><th>Sold</th><th>Avg/day</th><th>Days left</th><th>Reorder point</th><th>Suggested qty</th><th></th></tr></thead><tbody>
            <tr v-for="r in rows" :key="r.medicine_id"><td class="fw-semibold">{{r.name}} <small class="text-muted">{{r.strength}}</small></td><td>{{r.current_stock}} {{r.unit}}</td><td>{{r.incoming_units}}</td><td>{{r.units_sold}}</td><td>{{r.average_daily_sales}}</td><td><span class="badge" :class="r.days_of_stock !== null && r.days_of_stock <= 7 ? 'bg-danger' : 'bg-warning text-dark'">{{r.days_of_stock === null ? 'No sales' : `${r.days_of_stock} days`}}</span></td><td>{{r.reorder_point}}</td><td class="fw-bold text-success">{{r.suggested_quantity}}</td><td><router-link class="btn btn-sm btn-outline-success" :to="`/purchases/create?medicine=${r.medicine_id}`" title="Create purchase"><i class="bi bi-cart-plus"></i></router-link></td></tr>
            <tr v-if="!rows.length"><td colspan="9" class="text-center text-muted py-4">No reorder is currently recommended for this branch.</td></tr>
        </tbody></table></div></div>
        <p class="small text-muted mt-2">Formula considers average daily sales, reorder level, target coverage, non-expired branch stock, and quantities already on pending/approved purchase orders. Review quantities before ordering.</p>
    </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
const filters = reactive({history_days:30,lead_days:7,target_days:30});
const rows = ref([]);
async function load(){const {data}=await api.get('/reports/reorder-suggestions',{params:filters});rows.value=data.data||[];}
onMounted(load);
</script>
