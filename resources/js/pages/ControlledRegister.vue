<template>
    <div>
        <div class="card custom-card no-print mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label mb-1">From</label><input v-model="from" type="date" class="form-control"></div>
            <div><label class="form-label mb-1">To</label><input v-model="to" type="date" class="form-control"></div>
            <div style="min-width:240px"><label class="form-label mb-1">Medicine (optional)</label>
                <Autocomplete v-if="!medicine" url="/medicines/search" query-key="q" placeholder="Filter one medicine…" empty-text="Not found" @select="medicine = $event">
                    <template #item="{ item }"><b>{{ item.display_name || item.name }}</b> <small class="text-muted">{{ item.generic_name }}</small></template>
                </Autocomplete>
                <div v-else class="form-control d-flex justify-content-between align-items-center"><span class="text-truncate">{{ medicine.display_name || medicine.name }}</span><button class="btn btn-sm p-0 text-muted" @click="medicine = null"><i class="bi bi-x-lg"></i></button></div></div>
            <button class="btn btn-primary" :disabled="loading" @click="load"><i class="bi bi-funnel me-1"></i>Show</button>
            <button class="btn btn-light ms-auto" :disabled="!data" @click="printIt"><i class="bi bi-printer me-1"></i>Print register</button>
        </div></div>

        <div v-if="data" class="card custom-card" id="register-print">
            <div class="card-body">
                <div class="text-center mb-3">
                    <h5 class="mb-0 fw-bold">Controlled / Narcotics Drug Register</h5>
                    <div class="fw-semibold">{{ data.store.name }}</div>
                    <div class="small text-muted">{{ data.store.address }} <span v-if="data.store.license">· Drug licence {{ data.store.license }}</span></div>
                    <div class="small">Period: <b>{{ data.from }}</b> to <b>{{ data.to }}</b></div>
                </div>

                <h6 class="fw-semibold">Balance summary</h6>
                <div class="table-responsive mb-4"><table class="table table-bordered table-sm align-middle">
                    <thead class="table-light"><tr><th>Medicine</th><th class="text-end">Opening</th><th class="text-end">Received</th><th class="text-end">Dispensed</th><th class="text-end">Other out</th><th class="text-end">Closing</th></tr></thead>
                    <tbody>
                        <tr v-for="r in data.summary" :key="r.medicine_id"><td class="fw-semibold">{{ r.medicine }}<div class="small text-muted fw-normal">{{ r.generic_name }}</div></td>
                            <td class="text-end">{{ r.opening }}</td><td class="text-end">{{ r.received }}</td><td class="text-end">{{ r.dispensed }}</td><td class="text-end">{{ r.other_out }}</td><td class="text-end fw-bold" :class="r.closing < 0 ? 'text-danger' : ''">{{ r.closing }}</td></tr>
                        <tr v-if="!data.summary.length"><td colspan="6" class="text-center text-muted py-4">No controlled-drug movement in this period. Mark a medicine as “controlled / narcotic” in Medicines to track it here.</td></tr>
                    </tbody></table></div>

                <h6 class="fw-semibold">Entries (dispensing and stock movements)</h6>
                <div class="table-responsive"><table class="table table-bordered table-sm align-middle fs-12">
                    <thead class="table-light"><tr><th>Date / time</th><th>Medicine</th><th>Batch</th><th>Type</th><th class="text-end">Qty</th><th>Reference</th><th>Patient (CNIC)</th><th>Prescriber</th><th>Rx no</th><th>By</th></tr></thead>
                    <tbody>
                        <tr v-for="l in data.ledger" :key="l.id"><td class="text-nowrap">{{ (l.recorded_at || '').slice(0, 16).replace('T', ' ') }}</td><td>{{ l.medicine }} {{ l.strength }}</td><td>{{ l.batch_no || '—' }}</td>
                            <td class="text-capitalize">{{ l.transaction_type }}</td><td class="text-end fw-semibold" :class="l.quantity_change < 0 ? 'text-danger' : 'text-success'">{{ l.quantity_change > 0 ? '+' : '' }}{{ l.quantity_change }}</td>
                            <td>{{ l.reference_no }}</td><td>{{ l.customer || '—' }}<span v-if="l.customer_cnic"> ({{ l.customer_cnic }})</span></td><td>{{ l.doctor_name || '—' }}<span v-if="l.doctor_license"> / {{ l.doctor_license }}</span></td><td>{{ l.prescription_no || '—' }}</td><td>{{ l.recorded_by || '—' }}</td></tr>
                        <tr v-if="!data.ledger.length"><td colspan="10" class="text-center text-muted py-3">No entries.</td></tr>
                    </tbody></table></div>
                <div class="row mt-5 d-none d-print-flex"><div class="col-6">Pharmacist signature: ____________________</div><div class="col-6 text-end">Date: ____________________</div></div>
            </div>
        </div>
        <div v-else-if="loading" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import Autocomplete from '../components/Autocomplete.vue';

const today = new Date();
const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const from = ref(iso(new Date(today.getFullYear(), today.getMonth(), 1)));
const to = ref(iso(today));
const medicine = ref(null);
const data = ref(null);
const loading = ref(false);

async function load() {
    loading.value = true;
    try { data.value = (await api.get('/reports/controlled-register', { params: { from: from.value, to: to.value, medicine_id: medicine.value?.id || undefined } })).data; }
    catch (e) { data.value = null; } finally { loading.value = false; }
}
const printIt = () => window.print();
onMounted(load);
</script>
