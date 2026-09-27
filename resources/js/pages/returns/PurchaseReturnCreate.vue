<template>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Supplier *</label>
                    <select v-model="supplierId" class="form-select" required>
                        <option value="">— Select supplier —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} (payable {{ money(s.balance) }})</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Add medicine to return</label>
                    <Autocomplete url="/medicines/search" icon="bi-capsule" placeholder="Search medicine…" @select="addRow">
                        <template #item="{ item }"><b>{{ item.display_name }}</b> <small class="text-muted">stock {{ item.total_stock }}</small></template>
                    </Autocomplete>
                </div>
            </div></div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Medicine</th><th style="min-width:240px">Batch (qty available)</th><th style="width:100px">Qty</th><th class="text-end">Cost</th><th class="text-end">Total</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="(l, i) in rows" :key="l.key">
                                <td class="fw-semibold">{{ l.name }}</td>
                                <td>
                                    <select v-model="l.medicine_batch_id" class="form-select form-select-sm">
                                        <option v-for="b in l.batches" :key="b.id" :value="b.id">
                                            {{ b.batch_no }} · Exp {{ date(b.expiry_date) }}{{ b.is_expired ? ' EXPIRED' : '' }} · {{ b.quantity }} left{{ b.supplier ? ' · ' + b.supplier : '' }}
                                        </option>
                                    </select>
                                    <div v-if="!l.batches.length" class="small text-danger">No stock to return</div>
                                    <div v-else-if="batchOf(l) && supplierId && batchOf(l).supplier_id !== supplierId" class="small text-warning">This batch was bought from another supplier</div>
                                </td>
                                <td><input v-model.number="l.quantity" type="number" min="1" :max="batchOf(l)?.quantity" class="form-control form-control-sm"></td>
                                <td class="text-end">{{ money(batchOf(l)?.purchase_price) }}</td>
                                <td class="text-end fw-semibold">{{ money((batchOf(l)?.purchase_price || 0) * (l.quantity || 0)) }}</td>
                                <td><button class="btn btn-sm text-danger" @click="rows.splice(i, 1)"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            <tr v-if="!rows.length"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-truck-flatbed fs-1 d-block mb-2"></i>Add expired / damaged / extra medicines you are sending back</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card position-sticky" style="top:80px"><div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Units</span><b>{{ units }}</b></div>
                <div class="d-flex justify-content-between fs-5 fw-bold border-top pt-2 mb-3"><span>Return value</span><span>{{ money(total) }}</span></div>
                <label class="form-label small mb-0">Settlement</label>
                <select v-model="refundType" class="form-select form-select-sm mb-2">
                    <option value="adjust_balance">Reduce supplier payable (credit)</option>
                    <option value="cash">Supplier pays cash back</option>
                </select>
                <label class="form-label small mb-0">Reason</label>
                <input v-model="reason" class="form-control form-control-sm mb-3" placeholder="Expired / near expiry / damaged / wrong item">
                <button class="btn btn-danger w-100 btn-lg" :disabled="saving || !rows.length || !supplierId" @click="save">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-box-arrow-up"></i> Save Return
                </button>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Stock is taken OUT of the selected batch.</div>
            </div></div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';
import Autocomplete from '../../components/Autocomplete.vue';
import { date, money, stockChanged } from '../../utils/format';
import { confirm, notify } from '../../utils/swal';

const route = useRoute();
const router = useRouter();
const suppliers = ref([]);
const supplierId = ref(route.query.supplier ? Number(route.query.supplier) : '');
const rows = ref([]);
const refundType = ref('adjust_balance');
const reason = ref('');
const saving = ref(false);
let key = 0;

const batchOf = (l) => l.batches.find((b) => b.id === l.medicine_batch_id);
const units = computed(() => rows.value.reduce((s, l) => s + (l.quantity || 0), 0));
const total = computed(() => rows.value.reduce((s, l) => s + (batchOf(l)?.purchase_price || 0) * (l.quantity || 0), 0));

async function addRow(m) {
    const { data } = await api.get(`/medicines/${m.id}/batches`, { params: { supplier_id: supplierId.value || undefined } });
    rows.value.push({ key: ++key, name: m.display_name, batches: data.data, medicine_batch_id: data.data[0]?.id, quantity: 1 });
}

async function save() {
    if (!(await confirm({ title: 'Return to supplier?', text: `${units.value} unit(s) worth ${money(total.value)} will be removed from stock.`, confirmText: 'Yes, save', icon: 'question' }))) return;
    saving.value = true;
    try {
        const { data } = await api.post('/purchase-returns', {
            supplier_id: supplierId.value, refund_type: refundType.value, reason: reason.value,
            items: rows.value.filter((l) => l.medicine_batch_id).map((l) => ({ medicine_batch_id: l.medicine_batch_id, quantity: l.quantity })),
        });
        notify(data.message);
        stockChanged();
        router.push('/supplier-returns');
    } catch (e) { /* shown */ } finally { saving.value = false; }
}

onMounted(async () => { suppliers.value = (await api.get('/lookups')).data.suppliers; });
</script>
