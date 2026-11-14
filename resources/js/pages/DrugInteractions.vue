<template>
    <CrudPage endpoint="/interactions" singular="Interaction" add-label="Add interaction" search-placeholder="Search ingredient…" :fields="fields" :columns="columns"
              :defaults="{ severity: 'moderate', is_active: true }" delete-text="will be removed from the safety check." modal-size="lg">
        <template #top>
            <div class="alert alert-info small py-2"><i class="bi bi-info-circle me-1"></i>These pairs power the POS safety check. Use <b>single active ingredients</b> (e.g. “warfarin”, “ibuprofen”). The list is a starting reference – review it with your pharmacist and add anything specific to your formulary. It does not replace professional judgement.</div>
        </template>
        <template #cell-severity="{ item }"><span class="badge text-capitalize" :class="sev[item.severity]">{{ item.severity }}</span></template>
        <template #cell-pair="{ item }"><span class="fw-semibold text-capitalize">{{ item.ingredient_a }}</span> <i class="bi bi-plus text-muted"></i> <span class="fw-semibold text-capitalize">{{ item.ingredient_b }}</span></template>
        <template #cell-is_active="{ item }"><span class="badge" :class="item.is_active ? 'bg-success-transparent' : 'bg-secondary-transparent'">{{ item.is_active ? 'Active' : 'Off' }}</span></template>
    </CrudPage>
</template>

<script setup>
import CrudPage from '../components/CrudPage.vue';

const sev = { contraindicated: 'bg-danger', major: 'bg-danger-transparent', moderate: 'bg-warning-transparent', minor: 'bg-secondary-transparent' };
const fields = [
    { key: 'ingredient_a', label: 'Ingredient A', required: true, col: 'col-md-6', placeholder: 'warfarin' },
    { key: 'ingredient_b', label: 'Ingredient B', required: true, col: 'col-md-6', placeholder: 'ibuprofen' },
    { key: 'severity', label: 'Severity', type: 'select', col: 'col-md-6', options: [{ value: 'contraindicated', label: 'Contraindicated – never together' }, { value: 'major', label: 'Major' }, { value: 'moderate', label: 'Moderate' }, { value: 'minor', label: 'Minor' }] },
    { key: 'is_active', label: 'Active', type: 'switch', col: 'col-md-6' },
    { key: 'effect', label: 'What happens', required: true, type: 'textarea' },
    { key: 'management', label: 'What to do', type: 'textarea' },
];
const columns = [
    { key: 'pair', label: 'Combination' }, { key: 'severity', label: 'Severity' }, { key: 'effect', label: 'Effect' }, { key: 'management', label: 'Management' }, { key: 'is_active', label: 'Status' },
];
</script>
