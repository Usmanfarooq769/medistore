<template>
    <div>
        <div class="mb-3"><h4 class="fw-bold mb-1">Customer Receivables Ageing</h4><p class="text-muted small mb-0">Outstanding invoices grouped by age for the active branch.</p></div>
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Customer</th><th>Invoices</th><th>Current</th><th>1–30 days</th><th>31–60 days</th><th>61–90 days</th><th>Over 90</th><th>Total due</th><th></th></tr></thead><tbody>
            <tr v-for="r in rows" :key="r.customer_id"><td class="fw-semibold">{{r.customer_name}}<div class="small text-muted">{{r.phone}}</div></td><td>{{r.invoice_count}}</td><td>{{money(r.buckets.current)}}</td><td>{{money(r.buckets.days_1_30)}}</td><td>{{money(r.buckets.days_31_60)}}</td><td>{{money(r.buckets.days_61_90)}}</td><td class="text-danger">{{money(r.buckets.over_90)}}</td><td class="fw-bold text-danger">{{money(r.total_due)}}</td><td><router-link v-if="r.customer_id" :to="`/customers/${r.customer_id}`" class="btn btn-sm btn-outline-secondary">View</router-link></td></tr>
            <tr v-if="!rows.length"><td colspan="9" class="text-center text-muted py-4">No outstanding customer invoices.</td></tr>
        </tbody></table></div></div>
    </div>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import { money } from '../utils/format';
const rows=ref([]);
onMounted(async()=>{rows.value=(await api.get('/reports/customer-aging')).data.data||[];});
</script>
