<template>
    <div>
        <CrudPage ref="crud" endpoint="/customers" singular="Customer" add-label="Add Customer" search-placeholder="Name or phone…"
                  :fields="fields" :columns="columns" :can-delete="auth.isAdmin" :initial-filters="{ due: $route.query.due === '1' }">
            <template #filters="{ filters }">
                <div class="form-check form-switch mb-0"><input id="due" v-model="filters.due" class="form-check-input" type="checkbox"><label for="due" class="form-check-label">Only with due</label></div>
            </template>
            <template #cell-name="{ item }">
                <router-link :to="`/customers/${item.id}`" class="fw-semibold text-decoration-none">{{ item.name }}</router-link>
                <div class="small text-muted">{{ item.address }}</div>
            </template>
            <template #cell-sales_count="{ item }"><span class="badge bg-secondary">{{ item.sales_count }}</span></template>
            <template #cell-loyalty_points="{ item }"><span class="badge bg-info-subtle text-info">{{ item.loyalty_points || 0 }}</span></template>
            <template #cell-balance="{ item }"><span class="fw-semibold" :class="item.balance > 0 ? 'text-danger' : 'text-success'">{{ money(item.balance) }}</span></template>
            <template #actions="{ item }">
                <button v-if="item.balance > 0" class="btn btn-sm btn-outline-warning" title="Receive payment" @click="pay = item"><i class="bi bi-cash-coin"></i></button>
                <router-link :to="`/pos?customer=${item.id}`" class="btn btn-sm btn-outline-success ms-1" title="New sale for this customer"><i class="bi bi-cart-plus"></i></router-link>
                <router-link :to="`/customers/${item.id}`" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-eye"></i></router-link>
            </template>
        </CrudPage>
        <PaymentModal :show="!!pay" title="Receive payment" label="Due from" :name="pay?.name" :due="pay?.balance || 0" :methods="['cash', 'card', 'online']"
                      :endpoint="`/customers/${pay?.id}/payments`" @close="pay = null" @saved="crud.reload()" />
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import api from '../api';
import CrudPage from '../components/CrudPage.vue';
import PaymentModal from '../components/PaymentModal.vue';
import { useAuth } from '../stores/auth';
import { money } from '../utils/format';

const auth = useAuth();
const crud = ref(null);
const pay = ref(null);
const fields = reactive([
    { key: 'name', label: 'Name', required: true },
    { key: 'phone', label: 'Phone', col: 'col-md-6' },
    { key: 'address', label: 'Address', col: 'col-md-6' },
    ...(auth.isAdmin ? [{ key: 'credit_limit', label: 'Credit limit (blank = unlimited)', type: 'number', step: '0.01', min: 0, col: 'col-md-6', hint: 'Blank means unlimited credit.' }] : []),
]);
if (['admin', 'branch_manager'].includes(auth.user?.role)) {
    fields.push(
        { key: 'insurance_provider_id', label: 'Insurance provider', type: 'select', col: 'col-md-6', options: [{ value: '', label: 'No insurance provider' }] },
        { key: 'insurance_plan_id', label: 'Insurance plan', type: 'select', col: 'col-md-6', options: [{ value: '', label: 'No plan' }] },
        { key: 'insurance_member_no', label: 'Insurance member number', col: 'col-md-6' },
    );
}
onMounted(async () => {
    if (!['admin', 'branch_manager'].includes(auth.user?.role)) return;
    try {
        const { data } = await api.get('/stage4/insurance-providers');
        const providers = data.data || [];
        fields.find(f => f.key === 'insurance_provider_id').options = [
            { value: '', label: 'No insurance provider' },
            ...providers.map(p => ({ value: p.id, label: p.name })),
        ];
        fields.find(f => f.key === 'insurance_plan_id').options = [
            { value: '', label: 'No plan' },
            ...providers.flatMap(p => (p.plans || []).map(pl => ({ value: pl.id, label: `${p.name} — ${pl.name}` }))),
        ];
    } catch (e) {}
});
const columns = [
    { key: 'name', label: 'Customer' },
    { key: 'phone', label: 'Phone' },
    { key: 'sales_count', label: 'Invoices' },
    { key: 'loyalty_points', label: 'Points' },
    { key: 'balance', label: 'Due (receivable)' },
];
</script>
