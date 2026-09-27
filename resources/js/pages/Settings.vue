<template>
    <form class="row g-3" @submit.prevent="save">
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header fw-semibold">Store (printed on receipt)</div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label">Store name *</label><input v-model="s.store_name" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Address</label><input v-model="s.store_address" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input v-model="s.store_phone" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Drug license no</label><input v-model="s.license_no" class="form-control"></div>
                <div class="col-12"><label class="form-label">Receipt footer</label><input v-model="s.receipt_footer" class="form-control"></div>
            </div>
        </div></div>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header fw-semibold">Sales, returns & alerts</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Currency *</label><input v-model="s.currency" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Default tax %</label><input v-model="s.default_tax" type="number" step="0.01" min="0" max="100" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Max cashier discount %</label><input v-model="s.max_discount_percent" type="number" step="0.01" min="0" max="100" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Expiry alert (days before)</label><input v-model="s.expiry_alert_days" type="number" min="1" max="365" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Customer return allowed within (days)</label><input v-model="s.return_days" type="number" min="0" max="365" class="form-control"><div class="form-text">0 = no limit</div></div>
                <div class="col-12"><div class="form-check form-switch">
                    <input id="cpe" v-model="priceEdit" class="form-check-input" type="checkbox"><label for="cpe" class="form-check-label">Allow cashier to change sale price in POS</label>
                </div></div>
            </div>
        </div></div>
        <div class="col-12 text-end"><button class="btn btn-success btn-lg" :disabled="saving"><i class="bi bi-save"></i> Save settings</button></div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';
import { notify } from '../utils/swal';

const auth = useAuth();
const s = reactive({});
const saving = ref(false);
const priceEdit = computed({ get: () => s.cashier_price_edit === '1', set: (v) => { s.cashier_price_edit = v ? '1' : '0'; } });

onMounted(async () => {
    const { data } = await api.get('/settings');
    Object.assign(s, { default_tax: 0, max_discount_percent: 10, expiry_alert_days: 60, return_days: 7, cashier_price_edit: '0', ...data });
});

async function save() {
    saving.value = true;
    try {
        const { data } = await api.put('/settings', { ...s });
        auth.updateSettings(data.data);
        notify(data.message);
    } catch (e) { /* shown */ } finally { saving.value = false; }
}
</script>
