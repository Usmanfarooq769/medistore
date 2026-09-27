<template>
    <div class="row g-3">
        <div class="col-lg-8">
            <!-- mode switch -->
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="btn-group return-mode">
                        <button class="btn" :class="mode === 'invoice' ? 'btn-success' : 'btn-outline-secondary'" @click="switchMode('invoice')"><i class="bi bi-receipt"></i> With invoice</button>
                        <button class="btn" :class="mode === 'manual' ? 'btn-success' : 'btn-outline-secondary'" @click="switchMode('manual')"><i class="bi bi-hand-index"></i> Without invoice</button>
                    </div>
                    <div class="small text-muted">
                        <span class="badge bg-success">Good</span> = back to stock ·
                        <span class="badge bg-danger">Damaged</span> / <span class="badge bg-dark">Expired</span> = refund only, not restocked
                    </div>
                </div>
            </div>

            <!-- WITH INVOICE -->
            <div v-if="mode === 'invoice'" class="card">
                <div class="card-header">
                    <form class="input-group input-group-lg" @submit.prevent="findInvoice">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        <input ref="invoiceEl" v-model="invoiceNo" class="form-control" placeholder="Scan / type invoice no e.g. INV-20250101-00012" required>
                        <button class="btn btn-primary" :disabled="finding"><span v-if="finding" class="spinner-border spinner-border-sm"></span> Find</button>
                    </form>
                    <div class="small text-muted mt-1">Returns allowed within {{ returnDays }} day(s) of sale.</div>
                </div>
                <template v-if="sale">
                    <div class="px-3 pt-3 d-flex flex-wrap justify-content-between gap-2">
                        <div><b>{{ sale.invoice_no }}</b> · {{ date(sale.sale_date) }} · {{ sale.customer_name || 'Walk-in' }}</div>
                        <div>Total {{ money(sale.total) }} <span v-if="sale.due > 0" class="badge bg-danger ms-1">Due {{ money(sale.due) }}</span>
                            <button class="btn btn-sm btn-outline-warning ms-2" @click="returnAll">Return all</button></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Medicine</th><th>Batch / Expiry</th><th class="text-end">Net price</th><th class="text-center">Can return</th><th style="width:110px">Return qty</th><th style="width:140px">Condition</th><th class="text-end">Refund</th></tr></thead>
                            <tbody>
                                <tr v-for="l in lines" :key="l.sale_item_id" :class="{ 'opacity-50': !l.returnable }">
                                    <td class="fw-semibold">{{ l.name }}</td>
                                    <td class="small">{{ l.batch_no || '—' }}<div :class="daysLeft(l.expiry_date) < 0 ? 'text-danger' : 'text-muted'">{{ date(l.expiry_date) }}</div></td>
                                    <td class="text-end">{{ money(l.price) }}</td>
                                    <td class="text-center">{{ l.returnable }} <small class="text-muted">/ {{ l.sold }}</small></td>
                                    <td><input v-model.number="l.quantity" type="number" min="0" :max="l.returnable" :disabled="!l.returnable" class="form-control form-control-sm" @input="clampQty(l, l.returnable)"></td>
                                    <td>
                                        <select v-model="l.condition" class="form-select form-select-sm" :disabled="!l.returnable">
                                            <option value="good">Good (restock)</option><option value="damaged">Damaged</option><option value="expired">Expired</option>
                                        </select>
                                        <div v-if="l.condition === 'good' && daysLeft(l.expiry_date) < 0" class="small text-danger">Batch expired – not restocked</div>
                                    </td>
                                    <td class="text-end fw-semibold">{{ money(l.price * (l.quantity || 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
                <div v-else class="card-body text-center text-muted py-5"><i class="bi bi-receipt fs-1 d-block mb-2"></i>Enter the invoice number printed on the customer's receipt</div>
            </div>

            <!-- WITHOUT INVOICE -->
            <div v-else class="card">
                <div class="card-header row g-2 mx-0">
                    <div class="col-md-5 ps-0">
                        <label class="form-label small mb-1">Customer (optional)</label>
                        <Autocomplete v-if="!customer" url="/customers" query-key="search" :params="{ per_page: 8 }" icon="bi-person" placeholder="Search customer…" @select="customer = $event">
                            <template #item="{ item }"><b>{{ item.name }}</b> <small class="text-muted">{{ item.phone }}</small></template>
                        </Autocomplete>
                        <div v-else class="d-flex justify-content-between align-items-center border rounded px-2 py-2 small">
                            <span><i class="bi bi-person-check text-success"></i> <b>{{ customer.name }}</b> {{ customer.phone }}</span>
                            <button class="btn btn-sm p-0 text-muted" @click="customer = null"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <input v-if="!customer" v-model="customerName" class="form-control form-control-sm mt-1" placeholder="…or just type customer name">
                    </div>
                    <div class="col-md-7 pe-0">
                        <label class="form-label small mb-1">Add returned medicine</label>
                        <Autocomplete url="/medicines/search" icon="bi-capsule" placeholder="Search medicine / scan barcode…" @select="addManual">
                            <template #item="{ item }"><b>{{ item.display_name }}</b> <small class="text-muted">{{ item.generic_name }} · stock {{ item.total_stock }}</small></template>
                        </Autocomplete>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Medicine</th><th style="min-width:200px">Batch *</th><th style="width:110px">Price</th><th style="width:90px">Qty</th><th style="width:140px">Condition</th><th class="text-end">Refund</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="(l, i) in manual" :key="l.key">
                                <td class="fw-semibold">{{ l.name }}</td>
                                <td>
                                    <select v-model="l.medicine_batch_id" class="form-select form-select-sm" @change="onBatch(l)">
                                        <option v-for="b in l.batches" :key="b.id" :value="b.id">{{ b.batch_no || '—' }} · Exp {{ date(b.expiry_date) }}{{ b.is_expired ? ' (EXPIRED)' : '' }}</option>
                                    </select>
                                    <div v-if="!l.batches.length" class="small text-danger">No batch found – purchase first</div>
                                </td>
                                <td><input v-model.number="l.price" type="number" step="0.01" min="0" class="form-control form-control-sm"></td>
                                <td><input v-model.number="l.quantity" type="number" min="1" class="form-control form-control-sm"></td>
                                <td>
                                    <select v-model="l.condition" class="form-select form-select-sm">
                                        <option value="good">Good (restock)</option><option value="damaged">Damaged</option><option value="expired">Expired</option>
                                    </select>
                                    <div v-if="l.condition === 'good' && batchOf(l)?.is_expired" class="small text-danger">Batch expired – not restocked</div>
                                </td>
                                <td class="text-end fw-semibold">{{ money(l.price * (l.quantity || 0)) }}</td>
                                <td><button class="btn btn-sm text-danger" @click="manual.splice(i, 1)"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            <tr v-if="!manual.length"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-capsule fs-1 d-block mb-2"></i>Search the medicine the customer brought back</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SUMMARY -->
        <div class="col-lg-4">
            <div class="card position-sticky" style="top:80px">
                <div class="card-header fw-semibold"><i class="bi bi-arrow-counterclockwise"></i> Return summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1"><span>Units returned</span><b>{{ summary.units }}</b></div>
                    <div class="d-flex justify-content-between mb-1 small text-success"><span><i class="bi bi-box-arrow-in-down"></i> Back to stock</span><b>{{ summary.restock }}</b></div>
                    <div class="d-flex justify-content-between mb-2 small text-danger"><span><i class="bi bi-trash"></i> Not restocked (loss)</span><b>{{ summary.units - summary.restock }}</b></div>
                    <div class="d-flex justify-content-between mb-2"><span>Items value</span><b>{{ money(summary.value) }}</b></div>
                    <div class="mb-2">
                        <label class="form-label small mb-0">Deduction / restocking fee</label>
                        <input v-model.number="deduction" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="0">
                    </div>
                    <div class="d-flex justify-content-between fs-5 fw-bold border-top pt-2"><span>Return total</span><span>{{ money(summary.total) }}</span></div>
                    <div v-if="summary.dueAdjust > 0" class="d-flex justify-content-between small text-primary"><span>Adjusted against due</span><span>-{{ money(summary.dueAdjust) }}</span></div>
                    <div class="d-flex justify-content-between align-items-center p-2 rounded my-2 bg-warning-subtle">
                        <span class="fw-semibold">Refund to customer</span><span class="fw-bold fs-5">{{ money(refundMethod === 'none' ? 0 : summary.refund) }}</span>
                    </div>
                    <label class="form-label small mb-0">Refund method</label>
                    <div class="btn-group w-100 mb-2">
                        <button v-for="m in refundMethods" :key="m.key" type="button" class="btn btn-sm" :class="refundMethod === m.key ? 'btn-warning' : 'btn-outline-secondary'" @click="refundMethod = m.key">{{ m.label }}</button>
                    </div>
                    <label class="form-label small mb-0">Reason</label>
                    <input v-model="reason" class="form-control form-control-sm mb-1" placeholder="Why is the customer returning?">
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <button v-for="r in reasons" :key="r" type="button" class="chip" :class="{ active: reason === r }" style="font-size:.72rem" @click="reason = r">{{ r }}</button>
                    </div>
                    <button class="btn btn-warning btn-lg w-100 fw-semibold" :disabled="saving || !summary.units" @click="save">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-check2-circle"></i> Save Return
                    </button>
                    <router-link to="/returns" class="btn btn-link w-100 btn-sm mt-1">View all returns →</router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';
import Autocomplete from '../../components/Autocomplete.vue';
import { useAuth } from '../../stores/auth';
import { date, daysLeft, money, stockChanged } from '../../utils/format';
import { esc, Swal } from '../../utils/swal';

const auth = useAuth();
const route = useRoute();
const returnDays = computed(() => auth.settings.return_days || 7);

const mode = ref('invoice');
const invoiceNo = ref(route.query.invoice || '');
const invoiceEl = ref(null);
const finding = ref(false);
const sale = ref(null);
const lines = ref([]);

const manual = ref([]);
const customer = ref(null);
const customerName = ref('');
let key = 0;

const deduction = ref('');
const refundMethod = ref('cash');
const reason = ref('');
const saving = ref(false);
const refundMethods = [{ key: 'cash', label: 'Cash' }, { key: 'card', label: 'Card' }, { key: 'online', label: 'Online' }, { key: 'none', label: 'No refund' }];
const reasons = ['Wrong medicine', 'Doctor changed prescription', 'Extra quantity', 'Side effects', 'Damaged pack', 'Expired'];

function switchMode(m) {
    mode.value = m;
    if (m === 'invoice') nextTick(() => invoiceEl.value?.focus());
}

/* ---------- with invoice ---------- */
async function findInvoice() {
    finding.value = true;
    sale.value = null;
    lines.value = [];
    try {
        const { data } = await api.get('/sales/find', { params: { invoice_no: invoiceNo.value.trim() } });
        const s = data.data;
        const ratio = s.subtotal > 0 ? s.total / s.subtotal : 1;   // share invoice discount / tax
        sale.value = s;
        lines.value = s.items.map((i) => ({
            sale_item_id: i.id, name: i.medicine_name, batch_no: i.batch_no, expiry_date: i.expiry_date,
            price: Math.round(i.price * ratio * 100) / 100, sold: i.quantity, returnable: i.returnable_qty, quantity: 0,
            condition: daysLeft(i.expiry_date) < 0 ? 'expired' : 'good',
        }));
    } catch (e) { /* shown */ } finally { finding.value = false; }
}
function returnAll() { lines.value.forEach((l) => { l.quantity = l.returnable; }); }
function clampQty(l, max) { if (l.quantity > max) l.quantity = max; if (l.quantity < 0) l.quantity = 0; }

/* ---------- without invoice ---------- */
async function addManual(m) {
    const { data } = await api.get(`/medicines/${m.id}/batches`, { params: { all: 1 } });
    const batches = data.data;
    const first = batches.find((b) => !b.is_expired) || batches[0];
    manual.value.push({ key: ++key, medicine_id: m.id, name: m.display_name, batches, medicine_batch_id: first?.id, price: first?.sale_price ?? m.sale_price, quantity: 1, condition: 'good' });
}
const batchOf = (l) => l.batches.find((b) => b.id === l.medicine_batch_id);
function onBatch(l) { const b = batchOf(l); if (b) l.price = b.sale_price; }

/* ---------- summary ---------- */
const activeLines = computed(() => (mode.value === 'invoice' ? lines.value : manual.value).filter((l) => (l.quantity || 0) > 0));
const summary = computed(() => {
    const units = activeLines.value.reduce((s, l) => s + l.quantity, 0);
    const restock = activeLines.value.reduce((s, l) => {
        const expired = mode.value === 'invoice' ? daysLeft(l.expiry_date) < 0 : batchOf(l)?.is_expired;
        return s + (l.condition === 'good' && !expired ? l.quantity : 0);
    }, 0);
    const value = Math.round(activeLines.value.reduce((s, l) => s + l.price * l.quantity, 0) * 100) / 100;
    const total = Math.round(Math.max(value - (Number(deduction.value) || 0), 0) * 100) / 100;
    const dueAdjust = mode.value === 'invoice' && sale.value?.due > 0 ? Math.min(sale.value.due, total) : 0;
    return { units, restock, value, total, dueAdjust, refund: Math.round((total - dueAdjust) * 100) / 100 };
});

async function save() {
    if (mode.value === 'manual' && activeLines.value.some((l) => !l.medicine_batch_id)) {
        return Swal.fire({ icon: 'warning', title: 'Select batch', text: 'Choose a batch for every medicine.' });
    }
    const s = summary.value;
    const ok = await Swal.fire({
        icon: 'question', title: 'Save this return?',
        html: `<b>${s.units}</b> unit(s) returned<br><span class="text-success">${s.restock} will be added back to stock</span><br>
               Refund: <b class="fs-5">${money(refundMethod.value === 'none' ? 0 : s.refund)}</b>${s.dueAdjust ? `<br><small>(${money(s.dueAdjust)} adjusted against due)</small>` : ''}`,
        showCancelButton: true, confirmButtonText: 'Yes, save return', confirmButtonColor: '#ffc107', reverseButtons: true,
    });
    if (!ok.isConfirmed) return;

    const payload = mode.value === 'invoice'
        ? { sale_id: sale.value.id, items: activeLines.value.map((l) => ({ sale_item_id: l.sale_item_id, quantity: l.quantity, condition: l.condition })) }
        : {
            customer_id: customer.value?.id || null, customer_name: customer.value ? null : customerName.value || null,
            items: activeLines.value.map((l) => ({ medicine_id: l.medicine_id, medicine_batch_id: l.medicine_batch_id, quantity: l.quantity, price: l.price, condition: l.condition })),
        };

    saving.value = true;
    try {
        const { data } = await api.post('/sale-returns', { ...payload, deduction: Number(deduction.value) || 0, refund_method: refundMethod.value, reason: reason.value });
        const r = data.data;
        stockChanged();
        const x = await Swal.fire({
            icon: 'success', title: 'Return saved',
            html: `<div class="text-muted small">${esc(r.return_no)}</div><div class="display-6 fw-bold text-warning">${money(r.refund_amount)}</div>
                   <div class="small text-muted">refund to customer</div><div class="small mt-2">${esc(data.message)}</div>`,
            showDenyButton: true, confirmButtonText: '<i class="bi bi-printer"></i> Print credit note', denyButtonText: 'New return', confirmButtonColor: '#198754', denyButtonColor: '#6c757d',
        });
        if (x.isConfirmed) window.open(`/print/return/${r.id}?print=1`, '_blank', 'width=420,height=720');
        resetAll();
    } catch (e) { /* shown */ } finally { saving.value = false; }
}

function resetAll() {
    sale.value = null; lines.value = []; invoiceNo.value = ''; manual.value = []; customer.value = null; customerName.value = '';
    deduction.value = ''; reason.value = ''; refundMethod.value = 'cash';
    if (mode.value === 'invoice') nextTick(() => invoiceEl.value?.focus());
}

onMounted(() => {
    if (invoiceNo.value) findInvoice(); else invoiceEl.value?.focus();
});
</script>
