<template>
    <CrudPage endpoint="/categories" singular="Category" add-label="Add Category" search-placeholder="Search category…"
              :fields="[{ key: 'name', label: 'Name', required: true }, { key: 'description', label: 'Description' }]"
              :columns="[{ key: 'name', label: 'Name', class: 'fw-semibold' }, { key: 'description', label: 'Description', class: 'text-muted' }, { key: 'medicines_count', label: 'Medicines' }]">
        <template #cell-medicines_count="{ item }">
            <router-link :to="`/medicines?category_id=${item.id}`" class="badge bg-secondary text-decoration-none">{{ item.medicines_count }}</router-link>
        </template>
    </CrudPage>
</template>

<script setup>
import CrudPage from '../components/CrudPage.vue';
</script>
