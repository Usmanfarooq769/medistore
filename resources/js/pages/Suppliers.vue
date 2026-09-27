<template>
    <div>
        <CrudPage ref="crud" endpoint="/suppliers" singular="Supplier" add-label="Add Supplier" search-placeholder="Name, phone, contact…"
                  :fields="fields" :columns="columns" :defaults="{ opening_balance: 0 }">
            <template #filters="{ filters }">
                <div class="form-check form-switch mb-0"><input id="due" v-model="filters.due" class="form-check-input" type="checkbox"><label for="due" class="form-check-label">Only with due</label></div>
            </template>
            <template #cell-name="{ item }">
                <router-link :to="`/suppliers/${item.id}`" class="fw-semibold text-decoration-none">{{ item.name }}</router-link>
                <div class="small text-muted">{{ item.address }}</div>
            </template>
            <template #cell-purchases_count="{ item }"><span class="badge bg-secondary">{{ item.purchases_count }}</span></template>
            <template #cell-balance="{ item }"><span class="fw-semibold" :class="item.balance > 0 ? 'text-danger' : 'text-success'">{{ money(item.balance) }}</span></template>
            <template #actions="{ item }">
                <router-link :to="`/purchases/create?supplier=${item.id}`" class="btn btn-sm btn-outline-success" title="New purchase"><i class="bi bi-box-arrow-in-down"></i></router-link>
                <button v-if="item.balance > 0" class="btn btn-sm btn-outline-warning ms-1" title="Pay" @click="pay = item"><i class="bi bi-cash"></i></button>
                <router-link :to="`/suppliers/${item.id}`" class="btn btn-sm btn-outline-secondary ms-1" title="Ledger"><i class="bi bi-journal-text"></i></router-link>
            </template>
        </CrudPage>
        <PaymentModal :show="!!pay" title="Pay supplier" label="Payable to" :name="pay?.name" :due="pay?.balance || 0"
                      :endpoint="`/suppliers/${pay?.id}/payments`" @close="pay = null" @saved="crud.reload()" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import CrudPage from '../components/CrudPage.vue';
import PaymentModal from '../components/PaymentModal.vue';
import { money } from '../utils/format';

const crud = ref(null);
const pay = ref(null);
const fields = [
    { key: 'name', label: 'Supplier / distributor name', required: true },
    { key: 'contact_person', label: 'Contact person', col: 'col-md-6' },
    { key: 'phone', label: 'Phone', col: 'col-md-6' },
    { key: 'email', label: 'Email', type: 'email', col: 'col-md-6' },
    { key: 'opening_balance', label: 'Opening balance (we owe)', type: 'number', step: '0.01', min: 0, col: 'col-md-6' },
    { key: 'address', label: 'Address' },
];
const columns = [
    { key: 'name', label: 'Supplier' },
    { key: 'contact_person', label: 'Contact' },
    { key: 'phone', label: 'Phone' },
    { key: 'purchases_count', label: 'Purchases' },
    { key: 'balance', label: 'Payable (due)' },
];
</script>
