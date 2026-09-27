<template>
    <div v-if="c">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-body">
                <h5 class="fw-bold mb-1">{{ c.name }}</h5>
                <div class="small text-muted"><i class="bi bi-telephone"></i> {{ c.phone || '—' }} · <i class="bi bi-geo-alt"></i> {{ c.address || '—' }}</div>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="small text-muted">Due balance</div><div class="fs-4 fw-bold" :class="c.balance > 0 ? 'text-danger' : 'text-success'">{{ money(c.balance) }}</div></div>
                <button v-if="c.balance > 0" class="btn btn-warning" @click="payOpen = true"><i class="bi bi-cash-coin"></i> Receive payment</button>
            </div></div></div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Purchase history</div>
            <DataTable ref="table" :url="`/customers/${c.id}/sales`" :cols="8">
                <template #head><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Returned</th><th>Status</th><th></th></tr></template>
                <template #row="{ item }">
                    <tr>
                        <td class="fw-semibold">{{ item.invoice_no }}</td><td>{{ date(item.sale_date) }}</td><td>{{ money(item.total) }}</td><td>{{ money(item.paid) }}</td>
                        <td :class="{ 'text-danger fw-semibold': item.due > 0 }">{{ money(item.due) }}</td><td>{{ item.returned_amount ? money(item.returned_amount) : '—' }}</td>
                        <td><span class="badge" :class="statusClass(item.payment_status)">{{ item.payment_status }}</span></td>
                        <td class="text-nowrap">
                            <router-link :to="`/returns/new?invoice=${item.invoice_no}`" class="btn btn-sm btn-outline-warning" title="Return"><i class="bi bi-arrow-counterclockwise"></i></router-link>
                            <a :href="`/print/sale/${item.id}`" target="_blank" class="btn btn-sm btn-light ms-1"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>
        <PaymentModal :show="payOpen" title="Receive payment" label="Due from" :name="c.name" :due="c.balance" :methods="['cash', 'card', 'online']"
                      :endpoint="`/customers/${c.id}/payments`" @close="payOpen = false" @saved="load" />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import PaymentModal from '../components/PaymentModal.vue';
import { date, money, statusClass } from '../utils/format';

const route = useRoute();
const c = ref(null);
const payOpen = ref(false);
const table = ref(null);

async function load() {
    const { data } = await api.get(`/customers/${route.params.id}`);
    c.value = data.data;
    table.value?.reload();
}
onMounted(load);
</script>
