<template>
<div class="container-fluid px-0">
  <div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Mobile Dashboard</h4><div class="text-muted small">{{data.branch?.name || 'Assigned branch'}} · {{today}}</div></div><button class="btn btn-outline-secondary btn-sm" @click="load"><i class="bi bi-arrow-clockwise"></i></button></div>
  <div class="row g-2 mb-3">
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Today's sales</div><div class="fs-4 fw-bold">{{money(data.today?.sales_total)}}</div><div class="small text-muted">{{data.today?.sales_count||0}} invoices</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Outstanding today</div><div class="fs-4 fw-bold text-danger">{{money(data.today?.sales_due)}}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Low-stock medicines</div><div class="fs-4 fw-bold">{{data.low_stock||0}}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Expiring in 30 days</div><div class="fs-4 fw-bold text-warning">{{data.expiring_30_days||0}}</div></div></div></div>
  </div>
  <div class="d-grid gap-2 mb-3" style="grid-template-columns:repeat(2,minmax(0,1fr))">
    <router-link to="/pos" class="btn btn-primary btn-lg py-3"><i class="bi bi-cart-plus me-2"></i>New Sale</router-link>
    <router-link to="/sales" class="btn btn-outline-primary btn-lg py-3"><i class="bi bi-receipt me-2"></i>Sales</router-link>
    <router-link to="/alerts/low-stock" class="btn btn-outline-warning btn-lg py-3"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock</router-link>
    <router-link to="/alerts/expiry" class="btn btn-outline-danger btn-lg py-3"><i class="bi bi-calendar-x me-2"></i>Expiry</router-link>
  </div>
  <div class="card"><div class="card-header fw-semibold">Recent sales</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody><tr v-for="s in data.recent_sales||[]" :key="s.id"><td>{{s.invoice_no}}</td><td>{{s.customer_name||'Walk-in'}}</td><td>{{money(s.total)}}</td><td><span class="badge text-bg-light">{{s.payment_status}}</span></td></tr><tr v-if="!data.recent_sales?.length"><td colspan="4" class="text-center text-muted py-4">No sales yet.</td></tr></tbody></table></div></div>
  <div class="card mt-3"><div class="card-header fw-semibold"><i class="bi bi-stars me-2"></i>AI business assistant</div><div class="card-body"><textarea v-model="question" class="form-control mb-2" rows="2" placeholder="Ask about low stock, expiry or sales in your branch"></textarea><button class="btn btn-outline-primary btn-sm" :disabled="asking || !question.trim()" @click="ask">Ask</button><div v-if="answer" class="border rounded p-2 mt-2 small" style="white-space:pre-wrap">{{answer}}</div></div></div>
  <div class="small text-muted mt-2">Install MediStore from your browser's menu to use it like an app. Sales require a live connection so stock and payments remain consistent.</div>
</div>
</template>
<script setup>
import {ref,onMounted} from 'vue';import api from '../api';
const data=ref({}),today=new Date().toLocaleDateString(),question=ref(''),answer=ref(''),asking=ref(false);
const money=v=>new Intl.NumberFormat('en-PK',{style:'currency',currency:'PKR',maximumFractionDigits:0}).format(Number(v||0));
async function load(){const {data:response}=await api.get('/stage4/mobile/overview');data.value=response;}
async function ask(){asking.value=true;try{const {data:response}=await api.post('/stage4/ai/ask',{question:question.value});answer.value=response.answer;}finally{asking.value=false;}}
onMounted(load);
</script>