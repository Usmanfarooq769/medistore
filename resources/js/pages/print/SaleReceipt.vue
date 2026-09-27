<template>
    <div style="background:#eee; min-height:100vh; padding-top:1px">
        <div v-if="s" class="receipt">
            <div class="text-center">
                <div class="fw-bold fs-6">{{ st.store_name || 'MediStore' }}</div>
                <div>{{ st.store_address }}</div>
                <div>Ph: {{ st.store_phone }}</div>
                <div v-if="st.license_no">Lic: {{ st.license_no }}</div>
            </div>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Invoice</span><b>{{ s.invoice_no }}</b></div>
            <div class="d-flex justify-content-between"><span>Date</span><span>{{ dateTime(s.created_at) }}</span></div>
            <div class="d-flex justify-content-between"><span>Cashier</span><span>{{ s.user || '-' }}</span></div>
            <div class="d-flex justify-content-between"><span>Customer</span><span>{{ s.customer_name || 'Walk-in' }}</span></div>
            <div class="dashed"></div>
            <table>
                <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amt</th></tr></thead>
                <tbody>
                    <tr v-for="i in s.items" :key="i.id">
                        <td>{{ i.medicine_name }}<div style="font-size:10px">B:{{ i.batch_no }} E:{{ i.expiry_date ? i.expiry_date.slice(5, 7) + '/' + i.expiry_date.slice(2, 4) : '' }}</div></td>
                        <td class="text-center">{{ i.quantity }}</td><td class="text-end">{{ i.price.toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ s.subtotal.toFixed(2) }}</span></div>
            <div v-if="s.discount" class="d-flex justify-content-between"><span>Discount</span><span>-{{ s.discount.toFixed(2) }}</span></div>
            <div v-if="s.tax" class="d-flex justify-content-between"><span>Tax ({{ s.tax_percent }}%)</span><span>{{ s.tax.toFixed(2) }}</span></div>
            <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL</span><span>{{ money(s.total) }}</span></div>
            <div class="d-flex justify-content-between text-capitalize"><span>Paid ({{ s.payment_method }})</span><span>{{ s.paid.toFixed(2) }}</span></div>
            <div v-if="s.change_amount" class="d-flex justify-content-between"><span>Change</span><span>{{ s.change_amount.toFixed(2) }}</span></div>
            <div v-if="s.due" class="d-flex justify-content-between fw-bold"><span>Due</span><span>{{ s.due.toFixed(2) }}</span></div>
            <div v-if="s.returned_amount" class="d-flex justify-content-between"><span>Returned</span><span>-{{ s.returned_amount.toFixed(2) }}</span></div>
            <div class="dashed"></div>
            <div class="text-center">{{ st.receipt_footer || 'Thank you! Get well soon.' }}</div>
        </div>
        <div class="text-center no-print mb-4">
            <button class="btn btn-success me-2" onclick="window.print()">Print</button>
            <button class="btn btn-secondary" onclick="window.close()">Close</button>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';
import { useAuth } from '../../stores/auth';
import { dateTime, money } from '../../utils/format';

const route = useRoute();
const st = computed(() => useAuth().settings);
const s = ref(null);

onMounted(async () => {
    s.value = (await api.get(`/sales/${route.params.id}`)).data.data;
    if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 300); }
});
</script>
