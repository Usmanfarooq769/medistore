<template>
    <form class="row g-3" @submit.prevent="save">
        <div class="col-lg-9">
            <div class="card mb-3"><div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label">Supplier (distributor) *</label>
                    <div class="input-group">
                        <select v-model="form.supplier_id" class="form-select" required>
                            <option value="">— Select supplier —</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <router-link to="/suppliers" class="btn btn-outline-secondary" title="Manage suppliers"><i class="bi bi-plus"></i></router-link>
                    </div>
                    <div v-if="supplier" class="form-text">Current payable: <b class="text-danger">{{ money(supplier.balance) }}</b></div>
                </div>
                <div class="col-md-4"><label class="form-label">Supplier bill / invoice no</label><input v-model="form.supplier_invoice_no" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Purchase date *</label><input v-model="form.purchase_date" type="date" :max="today()" class="form-control" required></div>
            </div></div>

            <div class="card">
                <div class="card-header">
                    <label class="form-label small mb-1">Add medicine (name / generic / scan barcode, Enter to add)</label>
                    <Autocomplete url="/medicines/search" placeholder="Search medicine…" icon="bi-upc-scan" size="lg" empty-text="Not found – add it first in Medicines" @select="addRow">
                        <template #item="{ item }">
                            <div class="d-flex justify-content-between"><span><b>{{ item.display_name }}</b> <small class="text-muted">{{ item.generic_name }}</small></span>
                                <span class="small" :class="item.is_low ? 'text-danger' : 'text-muted'">Stock {{ item.total_stock }}</span></div>
                        </template>
                    </Autocomplete>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th style="min-width:170px">Medicine</th><th style="width:120px">Batch *</th><th style="width:140px">Expiry *</th><th style="width:80px">Qty *</th>
                            <th style="width:75px">Bonus</th><th style="width:105px">Cost *</th><th style="width:105px">Sale *</th><th class="text-end" style="width:100px">Total</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in form.items" :key="r.key">
                                <td><div class="fw-semibold small">{{ r.name }}</div><div class="small text-muted">Stock {{ r.stock }} {{ r.unit }}</div></td>
                                <td><input v-model="r.batch_no" class="form-control form-control-sm" required :ref="(el) => (i === form.items.length - 1 && el ? (lastBatch = el) : null)"></td>
                                <td><input v-model="r.expiry_date" type="date" :min="tomorrow" class="form-control form-control-sm" required></td>
                                <td><input v-model.number="r.quantity" type="number" min="1" class="form-control form-control-sm" required></td>
                                <td><input v-model.number="r.bonus_qty" type="number" min="0" class="form-control form-control-sm"></td>
                                <td><input v-model.number="r.purchase_price" type="number" step="0.01" min="0" class="form-control form-control-sm" required></td>
                                <td><input v-model.number="r.sale_price" type="number" step="0.01" min="0" class="form-control form-control-sm" required></td>
                                <td class="text-end fw-semibold">{{ (r.quantity * r.purchase_price || 0).toFixed(2) }}</td>
                                <td><button type="button" class="btn btn-sm text-danger" @click="form.items.splice(i, 1)"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            <tr v-if="!form.items.length"><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-box-seam fs-2 d-block mb-2"></i>Search and add medicines from the supplier bill</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card position-sticky" style="top:80px">
                <div class="card-header fw-semibold">Bill summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Items / units</span><span>{{ form.items.length }} / {{ units }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><b>{{ money(subtotal) }}</b></div>
                    <div class="mb-2"><label class="form-label small mb-0">Discount</label><input v-model.number="form.discount" type="number" step="0.01" min="0" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small mb-0">Tax / other charges</label><input v-model.number="form.tax" type="number" step="0.01" min="0" class="form-control form-control-sm"></div>
                    <div class="d-flex justify-content-between fs-5 fw-bold text-success border-top pt-2 mb-2"><span>Total</span><span>{{ money(total) }}</span></div>
                    <div class="mb-2"><label class="form-label small mb-0">Paid now</label>
                        <div class="input-group input-group-sm"><input v-model.number="form.paid" type="number" step="0.01" min="0" class="form-control">
                            <button type="button" class="btn btn-outline-success" @click="form.paid = total">Full</button></div></div>
                    <div class="mb-2"><label class="form-label small mb-0">Payment method</label>
                        <select v-model="form.payment_method" class="form-select form-select-sm"><option value="cash">Cash</option><option value="bank">Bank</option><option value="cheque">Cheque</option><option value="online">Online</option></select></div>
                    <div class="d-flex justify-content-between mb-3"><span>Due (added to supplier)</span><b class="text-danger">{{ money(Math.max(total - (form.paid || 0), 0)) }}</b></div>
                    <input v-model="form.note" class="form-control form-control-sm mb-3" placeholder="Note (optional)">
                    <button class="btn btn-success w-100 btn-lg" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-check2-circle"></i> Save Purchase</button>
                    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Saving creates batches, adds stock and updates the supplier balance.</div>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import Autocomplete from '../components/Autocomplete.vue';
import { money, shiftDate, stockChanged, today } from '../utils/format';
import { notify, Swal } from '../utils/swal';

const route = useRoute();
const router = useRouter();
const suppliers = ref([]);
const saving = ref(false);
const lastBatch = ref(null);
const tomorrow = shiftDate(today(), 1);
let key = 0;

const form = reactive({ supplier_id: route.query.supplier ? Number(route.query.supplier) : '', supplier_invoice_no: '', purchase_date: today(), discount: 0, tax: 0, paid: 0, payment_method: 'cash', note: '', items: [] });

const supplier = computed(() => suppliers.value.find((s) => s.id === form.supplier_id));
const subtotal = computed(() => form.items.reduce((s, r) => s + (r.quantity || 0) * (r.purchase_price || 0), 0));
const units = computed(() => form.items.reduce((s, r) => s + (r.quantity || 0) + (r.bonus_qty || 0), 0));
const total = computed(() => Math.round((Math.max(subtotal.value - (form.discount || 0), 0) + (form.tax || 0)) * 100) / 100);

async function addRow(m) {
    form.items.push({ key: ++key, medicine_id: m.id, name: m.display_name, unit: m.unit, stock: m.total_stock, batch_no: '', expiry_date: '', quantity: 1, bonus_qty: 0, purchase_price: m.purchase_price, sale_price: m.sale_price });
    await nextTick();
    lastBatch.value?.focus();
}

async function save() {
    if (!form.items.length) return Swal.fire({ icon: 'info', title: 'No items', text: 'Add at least one medicine.' });
    saving.value = true;
    try {
        const { data } = await api.post('/purchases', { ...form, items: form.items.map(({ key: _k, name: _n, unit: _u, stock: _s, ...r }) => r) });
        notify(data.message);
        stockChanged();
        router.push(`/purchases/${data.data.id}`);
    } catch (e) { /* shown */ } finally { saving.value = false; }
}

onMounted(async () => {
    suppliers.value = (await api.get('/lookups')).data.suppliers;
    if (route.query.medicine) {
        const { data } = await api.get(`/medicines/${route.query.medicine}`);
        addRow(data.data);
    }
});
</script>
