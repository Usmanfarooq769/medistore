<template>
    <div style="background:#eee; min-height:100vh; padding-top:1px">
        <div v-if="s" class="receipt" :class="`receipt-${width}`">
            <div class="text-center">
                <div class="fw-bold fs-6">{{ st.store_name || 'MediStore' }}</div>
                <div>{{ st.store_address }}</div>
                <div>Ph: {{ st.store_phone }}</div>
                <div v-if="st.license_no">Drug Lic: {{ st.license_no }}</div>
                <div v-if="st.store_ntn">NTN: {{ st.store_ntn }}<span v-if="st.store_strn"> · STRN: {{ st.store_strn }}</span></div>
            </div>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Invoice</span><b>{{ s.invoice_no }}</b></div>
            <div v-if="s.fbr_invoice_no" class="d-flex justify-content-between"><span>FBR Inv #</span><b>{{ s.fbr_invoice_no }}</b></div>
            <div class="d-flex justify-content-between"><span>Date</span><span>{{ dateTime(s.created_at) }}</span></div>
            <div class="d-flex justify-content-between"><span>Cashier</span><span>{{ s.user || '-' }}</span></div>
            <div class="d-flex justify-content-between"><span>Customer</span><span>{{ s.customer_name || 'Walk-in' }}</span></div>
            <div class="dashed"></div>
            <table>
                <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amt</th></tr></thead>
                <tbody>
                    <tr v-for="i in s.items" :key="i.id">
                        <td>{{ i.medicine_name }}<div style="font-size:10px">B:{{ i.batch_no }} E:{{ i.expiry_date ? i.expiry_date.slice(5, 7) + '/' + i.expiry_date.slice(2, 4) : '' }}</div></td>
                        <td class="text-center">{{ Number(i.sale_quantity || i.quantity).toLocaleString() }} {{ i.sale_unit === 'pack' ? 'pack' : 'unit' }}</td><td class="text-end">{{ (i.price * (i.sale_unit === 'pack' ? (i.pack_size || 1) : 1)).toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ s.subtotal.toFixed(2) }}</span></div>
            <div v-if="s.discount" class="d-flex justify-content-between"><span>Discount</span><span>-{{ s.discount.toFixed(2) }}</span></div>
            <div v-if="s.loyalty_points_redeemed" class="d-flex justify-content-between"><span>Points redeemed</span><span>{{ s.loyalty_points_redeemed }}</span></div>
            <div v-if="s.loyalty_points_earned" class="d-flex justify-content-between"><span>Points earned</span><span>{{ s.loyalty_points_earned }}</span></div>
            <div v-if="s.tax" class="d-flex justify-content-between"><span>Tax ({{ s.tax_percent }}%)</span><span>{{ s.tax.toFixed(2) }}</span></div>
            <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL</span><span>{{ money(s.total) }}</span></div>
            <div class="d-flex justify-content-between text-capitalize"><span>Paid ({{ s.payment_method }})</span><span>{{ s.paid.toFixed(2) }}</span></div>
            <div v-if="s.change_amount" class="d-flex justify-content-between"><span>Change</span><span>{{ s.change_amount.toFixed(2) }}</span></div>
            <div v-if="s.due" class="d-flex justify-content-between fw-bold"><span>Due</span><span>{{ s.due.toFixed(2) }}</span></div>
            <div v-if="s.returned_amount" class="d-flex justify-content-between"><span>Returned</span><span>-{{ s.returned_amount.toFixed(2) }}</span></div>
            <div class="dashed"></div>
            <div v-if="qr" class="text-center my-2">
                <img :src="qr" alt="Invoice QR" class="qr">
                <div style="font-size:9px">{{ s.fbr_invoice_no ? 'FBR verified invoice' : 'Scan to verify invoice' }}<span v-if="s.fbr_status === 'pending'"> (FBR sync pending)</span></div>
            </div>
            <div class="text-center">{{ st.receipt_footer || 'Thank you! Get well soon.' }}</div>
        </div>
        <div class="text-center no-print mb-4">
            <div class="btn-group me-2"><button v-for="w in ['58', '80', 'a4']" :key="w" class="btn btn-sm" :class="width === w ? 'btn-primary' : 'btn-outline-secondary'" @click="width = w">{{ w === 'a4' ? 'A4' : w + ' mm' }}</button></div>
            <button class="btn btn-primary me-2" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <button class="btn btn-secondary" onclick="window.close()">Close</button>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watchEffect } from 'vue';
import QRCode from 'qrcode';
import { useRoute } from 'vue-router';
import api from '../../api';
import { useAuth } from '../../stores/auth';
import { dateTime, money } from '../../utils/format';

const route = useRoute();
const st = computed(() => useAuth().settings);
const s = ref(null);
const width = ref(['58', '80', 'a4'].includes(String(st.value.receipt_width)) ? String(st.value.receipt_width) : '80');
const qr = ref('');

// paper size for the print dialog (thermal rolls print without margins)
watchEffect(() => {
    let el = document.getElementById('receipt-page-style');
    if (!el) { el = document.createElement('style'); el.id = 'receipt-page-style'; document.head.appendChild(el); }
    el.textContent = width.value === 'a4' ? '@page { size: A4; margin: 12mm }' : `@page { size: ${width.value}mm auto; margin: 2mm }`;
});

onMounted(async () => {
    s.value = (await api.get(`/sales/${route.params.id}`)).data.data;
    try { qr.value = await QRCode.toDataURL(s.value.fbr_invoice_no || `${st.value.store_name || 'MediStore'}|${s.value.invoice_no}|${s.value.total}`, { margin: 0, width: 160 }); } catch (e) { /* QR is optional */ }
    if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 300); }
});
</script>
