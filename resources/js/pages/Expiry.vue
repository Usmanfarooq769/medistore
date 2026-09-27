<template>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <select v-model="filters.type" class="form-select w-auto"><option value="">Expiring soon</option><option value="expired">Already expired</option></select>
            <select v-if="!filters.type" v-model="filters.days" class="form-select w-auto">
                <option v-for="d in [30, 60, 90, 180]" :key="d" :value="d">Within {{ d }} days</option>
            </select>
            <div class="ms-auto d-flex gap-2" v-if="auth.isAdmin">
                <router-link to="/supplier-returns/new" class="btn btn-outline-secondary"><i class="bi bi-truck-flatbed"></i> Return to supplier</router-link>
                <router-link to="/adjustments" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Write off</router-link>
            </div>
        </div>
        <DataTable url="/alerts/expiry" :filters="filters" :cols="7" :per-page="25" empty-text="Nothing here 🎉">
            <template #head><tr><th>Medicine</th><th>Batch</th><th>Supplier</th><th>Expiry</th><th>Days</th><th>Qty</th><th>Value (cost)</th></tr></template>
            <template #row="{ item: b }">
                <tr :class="{ 'table-danger': b.is_expired }">
                    <td class="fw-semibold">{{ b.medicine?.name }}</td><td>{{ b.batch_no }}</td><td class="small">{{ b.supplier || '—' }}</td><td>{{ date(b.expiry_date) }}</td>
                    <td><span class="badge" :class="b.days_left < 0 ? 'bg-danger' : b.days_left <= 30 ? 'bg-warning text-dark' : 'bg-info text-dark'">{{ b.days_left < 0 ? Math.abs(b.days_left) + 'd ago' : b.days_left + 'd left' }}</span></td>
                    <td>{{ b.quantity }}</td><td>{{ money(b.quantity * b.purchase_price) }}</td>
                </tr>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useRoute } from 'vue-router';
import DataTable from '../components/DataTable.vue';
import { useAuth } from '../stores/auth';
import { date, money } from '../utils/format';

const auth = useAuth();
const route = useRoute();
const filters = reactive({ type: route.query.type || '', days: Number(auth.settings.expiry_alert_days || 60) });
</script>
