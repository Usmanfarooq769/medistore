<template>
    <CrudPage endpoint="/users" singular="User" add-label="Add User" search-placeholder="Name or email…" :fields="fields" :columns="columns"
              :defaults="{ role: 'cashier', is_active: true }" delete-text="will be deactivated.">
        <template #top>
            <div class="alert alert-light border small py-2"><b>Admin</b>: full access · <b>Cashier</b>: POS, sales, customer returns, customers, alerts, daily closing</div>
        </template>
        <template #cell-role="{ item }"><span class="badge text-capitalize" :class="item.role === 'admin' ? 'bg-primary' : 'bg-info text-dark'">{{ item.role }}</span></template>
        <template #cell-is_active="{ item }"><span class="badge" :class="item.is_active ? 'bg-success' : 'bg-secondary'">{{ item.is_active ? 'Active' : 'Inactive' }}</span></template>
    </CrudPage>
</template>

<script setup>
import CrudPage from '../components/CrudPage.vue';

const fields = [
    { key: 'name', label: 'Name', required: true, col: 'col-md-6' },
    { key: 'email', label: 'Email', type: 'email', required: true, col: 'col-md-6' },
    { key: 'password', label: 'Password (min 6)', type: 'password', required: true, col: 'col-md-6' },
    { key: 'role', label: 'Role', type: 'select', col: 'col-md-6', options: [{ value: 'cashier', label: 'Cashier' }, { value: 'admin', label: 'Admin' }] },
    { key: 'is_active', label: 'Active', type: 'switch' },
];
const columns = [
    { key: 'name', label: 'Name', class: 'fw-semibold' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'is_active', label: 'Status' },
];
</script>
