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
            <template #cell-balance="{ item }"><span class="fw-semibold" :class="item.balance > 0 ? 'text-danger' : 'text-success'">{{ money(item.balance) }}</span></template>
            <template #actions="{ item }">
                <button v-if="item.balance > 0" class="btn btn-sm btn-outline-warning" title="Receive payment" @click="pay = item"><i class="bi bi-cash-coin"></i></button>
                <router-link :to="`/customers/${item.id}`" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-eye"></i></router-link>
            </template>
        </CrudPage>
        <PaymentModal :show="!!pay" title="Receive payment" label="Due from" :name="pay?.name" :due="pay?.balance || 0" :methods="['cash', 'card', 'online']"
                      :endpoint="`/customers/${pay?.id}/payments`" @close="pay = null" @saved="crud.reload()" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import CrudPage from '../components/CrudPage.vue';
import PaymentModal from '../components/PaymentModal.vue';
import { useAuth } from '../stores/auth';
import { money } from '../utils/format';

const auth = useAuth();
const crud = ref(null);
const pay = ref(null);
const fields = [
    { key: 'name', label: 'Name', required: true },
    { key: 'phone', label: 'Phone', col: 'col-md-6' },
    { key: 'address', label: 'Address', col: 'col-md-6' },
];
const columns = [
    { key: 'name', label: 'Customer' },
    { key: 'phone', label: 'Phone' },
    { key: 'sales_count', label: 'Invoices' },
    { key: 'balance', label: 'Due (receivable)' },
];
</script>
