<template>
    <CrudPage endpoint="/users" singular="User" modal-size="lg" add-label="Add User" search-placeholder="Name or email…" :fields="fields" :columns="columns"
              :defaults="{ role: 'cashier', is_active: true, branch_id: 1 }" delete-text="will be deactivated.">
        <template #top>
            <div class="alert alert-light border small py-2"><b>Admin</b>: head office and all branches · <b>Branch Manager</b>: assigned branch management · <b>Pharmacist</b>: prescription review and pharmacy workflows · <b>Cashier</b>: POS, sales, customer returns, customers, alerts, daily closing</div>
        </template>
        <template #form-extra="{ form }">
            <div class="col-12"><div class="fw-semibold text-primary border-bottom pb-1"><i class="bi bi-shield-lock me-1"></i>Approval PIN &amp; permissions</div></div>
            <div class="col-md-6">
                <label class="form-label">Manager PIN <small class="text-muted">(4–8 digits)</small></label>
                <input v-model="form.manager_pin" type="password" inputmode="numeric" maxlength="8" class="form-control" autocomplete="new-password" :placeholder="form.has_pin ? 'PIN is set – type a new one to change' : 'Leave blank for no PIN'">
                <div class="form-text">Lets this user approve big discounts, voids and drug-safety overrides on another person's screen.</div>
                <div v-if="form.has_pin" class="form-check mt-1"><input id="clrpin" v-model="form.clear_pin" class="form-check-input" type="checkbox"><label for="clrpin" class="form-check-label small">Remove the current PIN</label></div>
            </div>
            <div class="col-12">
                <label class="form-label mb-1">Permissions <small class="text-muted">– the role gives the default; tick or untick to override for this person</small></label>
                <div class="row g-1">
                    <div v-for="(label, key) in catalog.labels" :key="key" class="col-md-6">
                        <div class="form-check"><input :id="'perm_' + key" class="form-check-input" type="checkbox" :checked="effective(form, key)" :disabled="form.role === 'admin'" @change="form.permissions = { ...(form.permissions || {}), [key]: $event.target.checked }">
                            <label :for="'perm_' + key" class="form-check-label small">{{ label }}<span v-if="isOverride(form, key)" class="badge bg-warning-transparent ms-1">custom</span></label></div>
                    </div>
                </div>
                <div v-if="form.role === 'admin'" class="form-text">Admins always have every permission.</div>
            </div>
        </template>
        <template #cell-role="{ item }"><span class="badge text-capitalize" :class="item.role === 'admin' ? 'bg-primary' : 'bg-info text-dark'">{{ item.role }}</span></template>
        <template #cell-has_pin="{ item }"><span class="badge" :class="item.has_pin ? 'bg-success-transparent' : 'bg-secondary-transparent'"><i class="bi" :class="item.has_pin ? 'bi-key-fill' : 'bi-dash'"></i> {{ item.has_pin ? 'PIN set' : 'No PIN' }}</span></template>
        <template #cell-is_active="{ item }"><span class="badge" :class="item.is_active ? 'bg-success' : 'bg-secondary'">{{ item.is_active ? 'Active' : 'Inactive' }}</span></template>
    </CrudPage>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import CrudPage from '../components/CrudPage.vue';

const catalog = reactive({ labels: {}, roles: {} });
onMounted(async () => {
    try { Object.assign(catalog, (await api.get('/permission-catalog', { silent: true })).data); } catch (e) { /* admin only */ }
    try {
        const { data } = await api.get('/branches', { silent: true });
        const opts = (data.data || data).map((b) => ({ value: b.id, label: b.name }));
        const f = fields.find((x) => x.key === 'branch_id'); f.type = 'select'; f.options = opts; f.label = 'Branch';
    } catch (e) { /* keep numeric field */ }
});
const roleDefault = (role, key) => { const d = catalog.roles?.[role] || []; return d.includes('*') || d.includes(key); };
const effective = (form, key) => (form.permissions && key in form.permissions ? !!form.permissions[key] : roleDefault(form.role, key));
const isOverride = (form, key) => !!form.permissions && key in form.permissions && !!form.permissions[key] !== roleDefault(form.role, key);

const fields = reactive([
    { key: 'name', label: 'Name', required: true, col: 'col-md-6' },
    { key: 'email', label: 'Email', type: 'email', required: true, col: 'col-md-6' },
    { key: 'password', label: 'Password (min 8)', type: 'password', required: true, col: 'col-md-6' },
    { key: 'role', label: 'Role', type: 'select', col: 'col-md-6', options: [{ value: 'cashier', label: 'Cashier' }, { value: 'branch_manager', label: 'Branch Manager' }, { value: 'pharmacist', label: 'Pharmacist' }, { value: 'admin', label: 'Admin' }] },
    { key: 'branch_id', label: 'Branch ID', type: 'number', required: true, col: 'col-md-6' },
    { key: 'is_active', label: 'Active', type: 'switch' },
]);
const columns = [
    { key: 'name', label: 'Name', class: 'fw-semibold' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'has_pin', label: 'Approval PIN' },
    { key: 'is_active', label: 'Status' },
];
</script>
