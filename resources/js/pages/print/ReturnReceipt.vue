<template>
    <div style="background:#eee; min-height:100vh; padding-top:1px">
        <div v-if="r" class="receipt">
            <div class="text-center">
                <div class="fw-bold fs-6">{{ st.store_name || 'MediStore' }}</div>
                <div>{{ st.store_address }}</div>
                <div>Ph: {{ st.store_phone }}</div>
                <div class="fw-bold mt-1">*** CREDIT NOTE / RETURN ***</div>
            </div>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Return no</span><b>{{ r.return_no }}</b></div>
            <div class="d-flex justify-content-between"><span>Date</span><span>{{ dateTime(r.created_at) }}</span></div>
            <div class="d-flex justify-content-between"><span>Invoice</span><span>{{ r.invoice_no || 'Without invoice' }}</span></div>
            <div class="d-flex justify-content-between"><span>Customer</span><span>{{ r.customer_name || 'Walk-in' }}</span></div>
            <div class="d-flex justify-content-between"><span>By</span><span>{{ r.user }}</span></div>
            <div class="dashed"></div>
            <table>
                <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amt</th></tr></thead>
                <tbody>
                    <tr v-for="i in r.items" :key="i.id">
                        <td>{{ i.medicine_name }}<div style="font-size:10px">B:{{ i.batch_no }} · {{ i.condition }}{{ i.restocked ? ' · restocked' : '' }}</div></td>
                        <td class="text-center">{{ i.quantity }}</td><td class="text-end">{{ i.price.toFixed(2) }}</td><td class="text-end">{{ i.total.toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="dashed"></div>
            <div class="d-flex justify-content-between"><span>Items value</span><span>{{ r.subtotal.toFixed(2) }}</span></div>
            <div v-if="r.deduction" class="d-flex justify-content-between"><span>Deduction</span><span>-{{ r.deduction.toFixed(2) }}</span></div>
            <div class="d-flex justify-content-between fw-bold"><span>RETURN TOTAL</span><span>{{ money(r.total) }}</span></div>
            <div v-if="r.due_adjusted" class="d-flex justify-content-between"><span>Adjusted in due</span><span>{{ r.due_adjusted.toFixed(2) }}</span></div>
            <div class="d-flex justify-content-between fw-bold fs-6 text-capitalize"><span>REFUND ({{ r.refund_method }})</span><span>{{ money(r.refund_amount) }}</span></div>
            <div v-if="r.reason" class="mt-1">Reason: {{ r.reason }}</div>
            <div class="dashed"></div>
            <div class="text-center">Customer signature: ____________</div>
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
const r = ref(null);

onMounted(async () => {
    r.value = (await api.get(`/sale-returns/${route.params.id}`)).data.data;
    if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 300); }
});
</script>
