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
                <div class="col-md-6"><label class="form-label">Loyalty points per Rs 100 paid</label><input v-model.number="s.loyalty_earn_per_100" type="number" min="0" max="1000" class="form-control"><div class="form-text">Set 0 to disable earning points.</div></div>
                <div class="col-md-6"><label class="form-label">Value of 1 loyalty point (currency)</label><input v-model.number="s.loyalty_point_value" type="number" min="0.01" step="0.01" class="form-control"><div class="form-text">Example: 1 point = Rs 1 discount.</div></div>
                <div class="col-12"><label class="form-label">Daily report recipient email(s)</label><input v-model="s.daily_report_email" type="text" class="form-control" placeholder="owner@example.com, manager@example.com"><div class="form-text">Daily report is emailed automatically at 11:59 PM. Leave blank to send it to all Admin users.</div></div>
                <div class="col-12"><div class="form-check form-switch">
                    <input id="cpe" v-model="priceEdit" class="form-check-input" type="checkbox"><label for="cpe" class="form-check-label">Allow cashier to change sale price in POS</label>
                </div></div>
            </div>
        </div></div>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-printer me-1"></i>Receipt &amp; tax identity</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">Receipt paper</label>
                    <select v-model="s.receipt_width" class="form-select"><option value="80">Thermal 80 mm</option><option value="58">Thermal 58 mm</option><option value="a4">A4 page</option></select>
                    <div class="form-text">Print dialog is sized automatically.</div></div>
                <div class="col-md-6"><label class="form-label">Store NTN</label><input v-model="s.store_ntn" class="form-control" maxlength="30" placeholder="1234567-8"></div>
                <div class="col-md-6"><label class="form-label">STRN (sales-tax reg. no.)</label><input v-model="s.store_strn" class="form-control" maxlength="30"></div>
                <div class="col-12"><div class="form-check form-switch">
                    <input id="safe" v-model="safetyOn" class="form-check-input" type="checkbox"><label for="safe" class="form-check-label">Drug-safety check at POS (allergies, interactions, duplicate ingredients)</label>
                </div></div>
            </div>
        </div></div>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header fw-semibold d-flex justify-content-between"><span><i class="bi bi-qr-code me-1"></i>FBR POS invoicing</span>
                <div class="form-check form-switch mb-0"><input id="fbr" v-model="fbrOn" class="form-check-input" type="checkbox"><label for="fbr" class="form-check-label">Enabled</label></div></div>
            <div class="card-body row g-3" :class="{ 'opacity-50': !fbrOn }">
                <div class="col-md-6"><label class="form-label">POS ID</label><input v-model="s.fbr_pos_id" class="form-control" :disabled="!fbrOn" maxlength="30"></div>
                <div class="col-md-6"><label class="form-label">API token</label><input v-model="s.fbr_api_token" type="password" class="form-control" :disabled="!fbrOn" autocomplete="new-password" :placeholder="s.fbr_api_token_set === '1' ? 'Saved – type to replace' : 'Paste token'"></div>
                <div class="col-12"><label class="form-label">FBR API URL</label><input v-model="s.fbr_api_url" type="url" class="form-control" :disabled="!fbrOn" placeholder="https://… (leave blank to only number invoices locally)"></div>
                <div class="col-12 small text-muted">Every sale gets an FBR invoice number and a QR on the receipt. If FBR is unreachable the sale is never blocked – it is marked <b>pending</b> and re-sent automatically every 5 minutes. Use the POS ID, token and URL issued to your business and test in FBR's sandbox first.</div>
            </div>
        </div></div>
        <div class="col-12 text-end"><button class="btn btn-primary btn-lg" :disabled="saving"><i class="bi bi-save"></i> Save settings</button></div>
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
const safetyOn = computed({ get: () => s.safety_check_enabled !== '0', set: (v) => { s.safety_check_enabled = v ? '1' : '0'; } });
const fbrOn = computed({ get: () => s.fbr_enabled === '1', set: (v) => { s.fbr_enabled = v ? '1' : '0'; } });
const priceEdit = computed({ get: () => s.cashier_price_edit === '1', set: (v) => { s.cashier_price_edit = v ? '1' : '0'; } });

onMounted(async () => {
    const { data } = await api.get('/settings');
    Object.assign(s, { default_tax: 0, max_discount_percent: 10, expiry_alert_days: 60, return_days: 7, cashier_price_edit: '0', loyalty_earn_per_100: 1, loyalty_point_value: 1, receipt_width: '80', safety_check_enabled: '1', fbr_enabled: '0', fbr_api_token: '', ...data });
    s.fbr_api_token = '';
});

async function save() {
    saving.value = true;
    try {
        const { data } = await api.put('/settings', { ...s });
        auth.updateSettings(data.data);
        s.fbr_api_token = ''; Object.assign(s, data.data);
        notify(data.message);
    } catch (e) { /* shown */ } finally { saving.value = false; }
}
</script>
