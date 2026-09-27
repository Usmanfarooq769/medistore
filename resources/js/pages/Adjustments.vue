<template>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header fw-semibold">New adjustment</div>
                <div class="card-body">
                    <form @submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label">Medicine *</label>
                            <Autocomplete v-if="!medicine" url="/medicines/search" placeholder="Search medicine…" @select="pick">
                                <template #item="{ item }"><b>{{ item.display_name }}</b> <small class="text-muted">stock {{ item.total_stock }}</small></template>
                            </Autocomplete>
                            <div v-else class="d-flex justify-content-between border rounded px-2 py-2 small">
                                <span><b>{{ medicine.display_name }}</b> · stock {{ medicine.total_stock }}</span>
                                <button type="button" class="btn btn-sm p-0" @click="medicine = null; batches = []"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label">Batch *</label>
                            <select v-model="form.medicine_batch_id" class="form-select" required>
                                <option value="">{{ medicine ? 'Select batch' : 'Select medicine first' }}</option>
                                <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.batch_no }} · Exp {{ date(b.expiry_date) }}{{ b.is_expired ? ' (EXPIRED)' : '' }} · Qty {{ b.quantity }}</option>
                            </select></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label">Type *</label><select v-model="form.type" class="form-select"><option value="subtract">➖ Remove</option><option value="add">➕ Add</option></select></div>
                            <div class="col-6"><label class="form-label">Quantity *</label><input v-model.number="form.quantity" type="number" min="1" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Reason *</label><select v-model="form.reason" class="form-select">
                            <option value="expired">Expired</option><option value="damaged">Damaged / broken</option><option value="lost">Lost / theft</option>
                            <option value="correction">Physical count correction</option><option value="other">Other</option></select></div>
                        <div class="mb-3"><label class="form-label">Note</label><input v-model="form.note" class="form-control"></div>
                        <button class="btn btn-primary w-100" :disabled="saving"><i class="bi bi-check2"></i> Save adjustment</button>
                        <div class="small text-muted mt-2">Returning stock to a supplier? Use <router-link to="/supplier-returns/new">Return to Supplier</router-link>.</div>
                    </form>
                </div>
            </div>
            <div class="card mt-3"><div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-x-octagon text-danger"></i> Expired stock</div>
                <p class="small text-muted">Remove every expired batch from stock in one click.</p>
                <button class="btn btn-outline-danger w-100" @click="writeOff">Write off all expired</button>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><div class="row g-2">
                    <div class="col-md-4"><select v-model="filters.reason" class="form-select"><option value="">All reasons</option><option value="expired">Expired</option><option value="damaged">Damaged</option><option value="lost">Lost</option><option value="correction">Correction</option><option value="other">Other</option></select></div>
                    <div class="col-6 col-md-3"><input v-model="filters.from" type="date" class="form-control"></div>
                    <div class="col-6 col-md-3"><input v-model="filters.to" type="date" class="form-control"></div>
                </div></div>
                <DataTable ref="table" url="/adjustments" :filters="filters" :cols="6">
                    <template #head><tr><th>Date</th><th>Medicine</th><th>Batch</th><th>Qty</th><th>Reason</th><th>By</th></tr></template>
                    <template #row="{ item: a }">
                        <tr>
                            <td class="small">{{ dateTime(a.created_at) }}</td><td>{{ a.medicine }}</td>
                            <td class="small">{{ a.batch_no }}<div class="text-muted">{{ date(a.expiry_date) }}</div></td>
                            <td class="fw-semibold" :class="a.type === 'add' ? 'text-success' : 'text-danger'">{{ a.type === 'add' ? '+' : '-' }}{{ a.quantity }}</td>
                            <td><span class="badge bg-secondary text-capitalize">{{ a.reason }}</span><div class="small text-muted">{{ a.note }}</div></td>
                            <td class="small">{{ a.user }}</td>
                        </tr>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import Autocomplete from '../components/Autocomplete.vue';
import DataTable from '../components/DataTable.vue';
import { date, dateTime, stockChanged } from '../utils/format';
import { confirm, notify } from '../utils/swal';

const route = useRoute();
const table = ref(null);
const medicine = ref(null);
const batches = ref([]);
const saving = ref(false);
const filters = reactive({ reason: '', from: '', to: '' });
const form = reactive({ medicine_batch_id: '', type: 'subtract', quantity: 1, reason: 'expired', note: '' });

async function pick(m) {
    medicine.value = m;
    batches.value = (await api.get(`/medicines/${m.id}/batches`)).data.data;
    form.medicine_batch_id = batches.value[0]?.id || '';
}

async function save() {
    if (!medicine.value) return;
    saving.value = true;
    try {
        const { data } = await api.post('/adjustments', { ...form, medicine_id: medicine.value.id });
        notify(data.message);
        stockChanged();
        table.value.reload();
        await pick((await api.get(`/medicines/${medicine.value.id}`)).data.data);
        form.quantity = 1; form.note = '';
    } catch (e) { /* shown */ } finally { saving.value = false; }
}

async function writeOff() {
    if (!(await confirm({ title: 'Write off all expired stock?', text: 'All expired batches will become 0.', confirmText: 'Yes, write off' }))) return;
    try {
        const { data } = await api.post('/adjustments/write-off-expired');
        notify(data.message);
        stockChanged();
        table.value.reload();
    } catch (e) { /* shown */ }
}

onMounted(async () => {
    if (route.query.medicine) pick((await api.get(`/medicines/${route.query.medicine}`)).data.data);
});
</script>
