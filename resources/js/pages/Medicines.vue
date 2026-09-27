<template>
    <div>
        <div class="alert alert-light border small py-2"><i class="bi bi-lightbulb text-warning"></i>
            Step 1: add the medicine here. Step 2: add <b>stock</b> through <router-link to="/purchases/create">New Purchase</router-link> (batch + expiry). Stock is never typed by hand.</div>
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input v-model="filters.search" type="search" class="form-control" placeholder="Name, generic, barcode…"></div></div>
                    <div class="col-md-2"><select v-model="filters.category_id" class="form-select"><option value="">All categories</option>
                        <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                    <div class="col-md-2"><select v-model="filters.manufacturer_id" class="form-select"><option value="">All companies</option>
                        <option v-for="c in lookups.manufacturers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                    <div class="col-md-2"><select v-model="filters.status" class="form-select">
                        <option value="">All status</option><option value="low">Low stock</option><option value="out">Out of stock</option>
                        <option value="expiring">Has expiring batch</option><option value="expired">Has expired batch</option><option value="inactive">Inactive</option></select></div>
                    <div class="col-md-3 text-md-end"><button class="btn btn-success" @click="openForm()"><i class="bi bi-plus-lg"></i> Add Medicine</button></div>
                </div>
            </div>
            <DataTable ref="table" url="/medicines" :filters="filters" :cols="7" default-sort="name">
                <template #head="{ sortBy, icon }">
                    <tr>
                        <th class="sortable" @click="sortBy('name')">Medicine <i :class="['bi', icon('name')]"></i></th>
                        <th>Category / Company</th>
                        <th class="sortable" @click="sortBy('sale_price')">Price <i :class="['bi', icon('sale_price')]"></i></th>
                        <th class="sortable" @click="sortBy('total_stock')">Stock <i :class="['bi', icon('total_stock')]"></i></th>
                        <th>Nearest expiry</th><th>Rack</th><th class="text-end">Actions</th>
                    </tr>
                </template>
                <template #row="{ item: m }">
                    <tr :class="{ 'opacity-50': !m.is_active }">
                        <td><router-link :to="`/medicines/${m.id}`" class="fw-semibold text-decoration-none">{{ m.display_name }}</router-link>
                            <div class="small text-muted">{{ m.generic_name }} <span v-if="m.barcode">· <i class="bi bi-upc"></i> {{ m.barcode }}</span></div></td>
                        <td class="small">{{ m.category || '—' }}<div class="text-muted">{{ m.manufacturer }}</div></td>
                        <td><div class="fw-semibold">{{ money(m.sale_price) }}</div><div class="small text-muted">Cost {{ money(m.purchase_price) }}</div></td>
                        <td>
                            <span class="fw-semibold" :class="m.total_stock <= 0 ? 'text-danger' : m.is_low ? 'text-warning-emphasis' : ''">{{ m.total_stock }}</span> <small class="text-muted">{{ m.unit }}</small>
                            <div class="stock-bar mt-1"><div :style="{ width: Math.min(100, m.total_stock / Math.max(m.reorder_level * 3, 1) * 100) + '%', background: m.total_stock <= 0 ? '#dc3545' : m.is_low ? '#ffc107' : '#198754' }"></div></div>
                        </td>
                        <td>
                            <template v-if="m.nearest_expiry">
                                <span :class="daysLeft(m.nearest_expiry) < 0 ? 'text-danger fw-bold' : daysLeft(m.nearest_expiry) <= 60 ? 'text-warning-emphasis fw-semibold' : ''">{{ date(m.nearest_expiry) }}</span>
                                <span v-if="daysLeft(m.nearest_expiry) < 0" class="badge bg-danger ms-1">Expired</span>
                                <span v-else-if="daysLeft(m.nearest_expiry) <= 60" class="badge bg-warning text-dark ms-1">{{ daysLeft(m.nearest_expiry) }}d</span>
                            </template><span v-else>—</span>
                        </td>
                        <td>{{ m.rack || '—' }}</td>
                        <td class="text-end text-nowrap">
                            <router-link :to="`/purchases/create?medicine=${m.id}`" class="btn btn-sm btn-outline-success" title="Purchase / add stock"><i class="bi bi-box-arrow-in-down"></i></router-link>
                            <router-link :to="`/medicines/${m.id}`" class="btn btn-sm btn-outline-secondary ms-1" title="Stock card"><i class="bi bi-card-list"></i></router-link>
                            <button class="btn btn-sm btn-outline-primary ms-1" title="Edit" @click="openForm(m)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger ms-1" title="Delete" @click="remove(m)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>

        <Modal :show="show" :title="editId ? 'Edit Medicine' : 'Add Medicine'" size="lg" @close="show = false">
            <form class="row g-3" @submit.prevent="save">
                <div class="col-md-5"><label class="form-label">Brand name *</label><input v-model="form.name" class="form-control" required placeholder="Panadol"></div>
                <div class="col-md-3"><label class="form-label">Strength</label><input v-model="form.strength" class="form-control" placeholder="500mg"></div>
                <div class="col-md-4"><label class="form-label">Generic / formula</label><input v-model="form.generic_name" class="form-control" placeholder="Paracetamol"></div>
                <div class="col-md-4"><label class="form-label">Category</label><select v-model="form.category_id" class="form-select"><option :value="null">—</option>
                    <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div class="col-md-4"><label class="form-label">Company (manufacturer)</label><select v-model="form.manufacturer_id" class="form-select"><option :value="null">—</option>
                    <option v-for="c in lookups.manufacturers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div class="col-md-4"><label class="form-label">Unit *</label><select v-model="form.unit" class="form-select">
                    <option v-for="u in units" :key="u" :value="u" class="text-capitalize">{{ u }}</option></select></div>
                <div class="col-md-4"><label class="form-label">Barcode</label><input v-model="form.barcode" class="form-control" placeholder="Scan or type"></div>
                <div class="col-md-4"><label class="form-label">Purchase price *</label><input v-model="form.purchase_price" type="number" step="0.01" min="0" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Sale price (MRP) *</label><input v-model="form.sale_price" type="number" step="0.01" min="0" class="form-control" required>
                    <div v-if="form.sale_price && form.purchase_price" class="form-text" :class="margin < 0 ? 'text-danger' : 'text-success'">Margin {{ money(margin) }} ({{ marginPct }}%)</div></div>
                <div class="col-md-4"><label class="form-label">Re-order level *</label><input v-model="form.reorder_level" type="number" min="0" class="form-control" required><div class="form-text">Low-stock alert at ≤ this</div></div>
                <div class="col-md-4"><label class="form-label">Rack / shelf</label><input v-model="form.rack" class="form-control"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input id="act" v-model="form.is_active" class="form-check-input" type="checkbox"><label class="form-check-label" for="act">Active</label></div></div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-light me-2" @click="show = false">Cancel</button>
                    <button class="btn btn-success" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>Save</button>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import Modal from '../components/Modal.vue';
import { date, daysLeft, money, stockChanged } from '../utils/format';
import { confirm, notify, Swal } from '../utils/swal';

const route = useRoute();
const router = useRouter();
const table = ref(null);
const lookups = reactive({ categories: [], manufacturers: [] });
const filters = reactive({ search: '', category_id: route.query.category_id || '', manufacturer_id: route.query.manufacturer_id || '', status: route.query.status || '' });
const units = ['strip', 'tablet', 'capsule', 'box', 'bottle', 'tube', 'vial', 'ampoule', 'sachet', 'pcs'];

const show = ref(false);
const saving = ref(false);
const editId = ref(null);
const blank = { name: '', strength: '', generic_name: '', category_id: null, manufacturer_id: null, unit: 'strip', barcode: '', purchase_price: '', sale_price: '', reorder_level: 10, rack: '', is_active: true };
const form = reactive({ ...blank });
const margin = computed(() => Number(form.sale_price) - Number(form.purchase_price));
const marginPct = computed(() => (Number(form.purchase_price) ? ((margin.value / Number(form.purchase_price)) * 100).toFixed(1) : 0));

function openForm(m = null) {
    editId.value = m?.id || null;
    Object.assign(form, blank, m ? Object.fromEntries(Object.keys(blank).map((k) => [k, m[k] ?? blank[k]])) : {});
    show.value = true;
}

async function save() {
    saving.value = true;
    try {
        const { data } = editId.value ? await api.put(`/medicines/${editId.value}`, { ...form }) : await api.post('/medicines', { ...form });
        show.value = false;
        table.value.reload();
        if (!editId.value) {
            const r = await Swal.fire({ icon: 'success', title: 'Medicine added', text: 'Add opening stock now?', showCancelButton: true, confirmButtonText: 'Yes, New Purchase', cancelButtonText: 'Later', confirmButtonColor: '#198754' });
            if (r.isConfirmed) router.push(`/purchases/create?medicine=${data.data.id}`);
        } else notify(data.message);
    } catch (e) { /* shown */ } finally { saving.value = false; }
}

async function remove(m) {
    if (!(await confirm({ text: `Delete ${m.display_name}? (If it has history it will be deactivated.)`, confirmText: 'Yes, delete' }))) return;
    try {
        const { data } = await api.delete(`/medicines/${m.id}`);
        notify(data.message);
        table.value.reload();
        stockChanged();
    } catch (e) { /* shown */ }
}

onMounted(async () => {
    const { data } = await api.get('/lookups');
    Object.assign(lookups, data);
    if (route.query.new) openForm();
});
</script>
