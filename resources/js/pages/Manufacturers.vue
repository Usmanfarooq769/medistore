<template>
    <CrudPage endpoint="/manufacturers" singular="Company" add-label="Add Company" search-placeholder="Search company…"
              :fields="fields" :columns="columns">
        <template #top>
            <div class="alert alert-info py-2 small"><i class="bi bi-info-circle"></i> <b>Company</b> = who <u>makes</u> the medicine (GSK, Abbott…). <b>Supplier</b> = distributor you <u>buy</u> from.</div>
        </template>
        <template #cell-medicines_count="{ item }">
            <router-link :to="`/medicines?manufacturer_id=${item.id}`" class="badge bg-secondary text-decoration-none">{{ item.medicines_count }}</router-link>
        </template>
    </CrudPage>
</template>

<script setup>
import CrudPage from '../components/CrudPage.vue';

const fields = [
    { key: 'name', label: 'Company name', required: true },
    { key: 'phone', label: 'Phone', col: 'col-md-6' },
    { key: 'email', label: 'Email', type: 'email', col: 'col-md-6' },
    { key: 'address', label: 'Address' },
];
const columns = [
    { key: 'name', label: 'Company', class: 'fw-semibold' },
    { key: 'phone', label: 'Phone' },
    { key: 'email', label: 'Email' },
    { key: 'address', label: 'Address', class: 'small text-muted' },
    { key: 'medicines_count', label: 'Medicines' },
];
</script>
