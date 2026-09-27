<template>
    <div v-if="s">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card h-100"><div class="card-body">
                <h5 class="fw-bold mb-1">{{ s.name }}</h5>
                <div class="small text-muted"><i class="bi bi-person"></i> {{ s.contact_person || '—' }} · <i class="bi bi-telephone"></i> {{ s.phone || '—' }}</div>
                <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ s.address || '—' }}</div>
            </div></div></div>
            <div class="col-md-8"><div class="row g-3">
                <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Purchases</div><div class="fw-bold">{{ money(summary.purchases) }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Paid</div><div class="fw-bold text-success">{{ money(summary.paid) }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Returned</div><div class="fw-bold text-warning">{{ money(summary.returns) }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Payable</div><div class="fw-bold text-danger">{{ money(s.balance) }}</div></div></div></div>
            </div></div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Ledger</span>
                <div class="d-flex gap-2">
                    <router-link :to="`/purchases/create?supplier=${s.id}`" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-down"></i> Purchase</router-link>
                    <router-link :to="`/supplier-returns/new?supplier=${s.id}`" class="btn btn-sm btn-outline-secondary"><i class="bi bi-truck-flatbed"></i> Return</router-link>
                    <button v-if="s.balance > 0" class="btn btn-sm btn-warning" @click="payOpen = true"><i class="bi bi-cash"></i> Pay</button>
                </div>
            </div>
            <DataTable ref="table" :url="`/suppliers/${s.id}/ledger`" :cols="6">
                <template #head><tr><th>Date</th><th>Type</th><th>Reference</th><th>Note</th><th class="text-end">Purchase (Dr)</th><th class="text-end">Paid / Return (Cr)</th></tr></template>
                <template #row="{ item }">
                    <tr>
                        <td>{{ date(item.date) }}</td>
                        <td><span class="badge text-capitalize" :class="{ purchase: 'bg-primary', payment: 'bg-success', return: 'bg-warning text-dark' }[item.type]">{{ item.type }}</span></td>
                        <td><router-link v-if="item.type === 'purchase'" :to="`/purchases/${item.id}`">{{ item.ref }}</router-link><span v-else class="text-capitalize">{{ item.ref }}</span></td>
                        <td class="small text-muted">{{ item.note }}</td>
                        <td class="text-end">{{ Number(item.debit) ? money(item.debit) : '' }}</td>
                        <td class="text-end text-success">{{ Number(item.credit) ? money(item.credit) : '' }}</td>
                    </tr>
                </template>
            </DataTable>
        </div>
        <PaymentModal :show="payOpen" title="Pay supplier" label="Payable to" :name="s.name" :due="s.balance" :endpoint="`/suppliers/${s.id}/payments`" @close="payOpen = false" @saved="load" />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';
import DataTable from '../components/DataTable.vue';
import PaymentModal from '../components/PaymentModal.vue';
import { date, money } from '../utils/format';

const route = useRoute();
const s = ref(null);
const summary = ref({});
const payOpen = ref(false);
const table = ref(null);

async function load() {
    const { data } = await api.get(`/suppliers/${route.params.id}`);
    s.value = data.data;
    summary.value = data.summary;
    table.value?.reload();
}
onMounted(load);
</script>
