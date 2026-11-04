<template>
  <div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div><h4 class="mb-1">Enterprise Platform</h4><div class="text-muted small">Partner integrations, customer self-service and branch subscriptions</div></div>
      <button class="btn btn-outline-success btn-sm" @click="loadAll"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
    <ul class="nav nav-pills gap-2 mb-3 flex-wrap">
      <li class="nav-item"><button class="nav-link" :class="{active:tab==='partners'}" @click="tab='partners'">Partner APIs</button></li>
      <li class="nav-item"><button class="nav-link" :class="{active:tab==='subscriptions'}" @click="tab='subscriptions'">Subscriptions</button></li>
      <li class="nav-item"><button class="nav-link" :class="{active:tab==='notifications'}" @click="tab='notifications'">Notifications</button></li>
    </ul>

    <section v-if="tab==='partners'" class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Partner API clients</strong><button class="btn btn-success btn-sm" @click="showPartner=true">Add partner</button></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Client key</th><th>Scopes</th><th>Status</th><th>Last used</th><th></th></tr></thead><tbody>
        <tr v-for="p in partners" :key="p.id"><td class="fw-semibold">{{p.name}}</td><td><code>{{p.client_key}}</code></td><td><span v-for="s in parse(p.scopes)" :key="s" class="badge text-bg-light me-1">{{s}}</span></td><td><span class="badge" :class="p.status==='active'?'text-bg-success':'text-bg-secondary'">{{p.status}}</span></td><td>{{p.last_used_at || 'Never'}}</td><td><button class="btn btn-sm btn-outline-secondary" @click="togglePartner(p)">{{p.status==='active'?'Suspend':'Activate'}}</button></td></tr>
        <tr v-if="!partners.length"><td colspan="6" class="text-center text-muted py-4">No partner clients created.</td></tr>
      </tbody></table></div>
      <div class="card-footer small text-muted">Partner secrets are displayed only once at creation. Store them in the partner's secure secret manager.</div>
    </section>

    <section v-if="tab==='subscriptions'" class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Branch subscriptions</strong><button class="btn btn-success btn-sm" @click="showSubscription=true">Assign plan</button></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Branch</th><th>Plan</th><th>Cycle</th><th>Status</th><th>Start</th><th>End</th><th></th></tr></thead><tbody>
        <tr v-for="s in subscriptions" :key="s.id"><td>{{s.branch_name}}</td><td>{{s.plan_name}}</td><td class="text-capitalize">{{s.billing_cycle}}</td><td><span class="badge text-bg-light">{{s.status}}</span></td><td>{{s.starts_on}}</td><td>{{s.ends_on || '—'}}</td><td><button class="btn btn-sm btn-outline-success" @click="generateInvoice(s)">Generate invoice</button></td></tr>
        <tr v-if="!subscriptions.length"><td colspan="7" class="text-center text-muted py-4">No branch subscriptions assigned.</td></tr>
      </tbody></table></div>
      <div class="card-footer small text-muted">Subscription payment records are administrative records; confirm receipt with your payment provider before marking an invoice paid.</div>
    </section>

    <section v-if="tab==='notifications'" class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between"><strong>My notifications</strong><button class="btn btn-sm btn-outline-success" @click="readAll">Mark all read</button></div>
      <div v-for="n in notifications" :key="n.id" class="p-3 border-bottom d-flex justify-content-between gap-3"><div><div class="fw-semibold">{{n.title}}</div><div class="small text-muted">{{n.body}}</div><div class="small text-muted mt-1">{{n.created_at}}</div></div><button v-if="!n.read_at" class="btn btn-sm btn-outline-secondary align-self-center" @click="readOne(n)">Mark read</button><span v-else class="badge text-bg-light align-self-center">Read</span></div>
      <div v-if="!notifications.length" class="text-center text-muted py-4">No notifications yet.</div>
    </section>

    <div v-if="showSubscription" class="modal d-block" tabindex="-1" style="background:#0007"><div class="modal-dialog"><form class="modal-content" @submit.prevent="assignSubscription"><div class="modal-header"><h5 class="modal-title">Assign branch subscription</h5><button type="button" class="btn-close" @click="showSubscription=false"/></div><div class="modal-body"><label class="form-label">Branch</label><select v-model.number="subscriptionForm.branch_id" class="form-select mb-3" required><option value="">Select branch</option><option v-for="b in branches" :key="b.id" :value="b.id">{{b.name}}</option></select><label class="form-label">Plan</label><select v-model.number="subscriptionForm.plan_id" class="form-select mb-3" required><option value="">Select plan</option><option v-for="p in plans" :key="p.id" :value="p.id">{{p.name}} — Rs {{p.monthly_price}} / month</option></select><label class="form-label">Billing cycle</label><select v-model="subscriptionForm.billing_cycle" class="form-select mb-3"><option value="monthly">Monthly</option><option value="annual">Annual</option></select><label class="form-label">Start date</label><input v-model="subscriptionForm.starts_on" type="date" class="form-control" required></div><div class="modal-footer"><button type="button" class="btn btn-light" @click="showSubscription=false">Cancel</button><button class="btn btn-success">Assign</button></div></form></div></div>
    <div v-if="showPartner" class="modal d-block" tabindex="-1" style="background:#0007"><div class="modal-dialog"><form class="modal-content" @submit.prevent="createPartner"><div class="modal-header"><h5 class="modal-title">Create partner API client</h5><button type="button" class="btn-close" @click="showPartner=false"/></div><div class="modal-body"><label class="form-label">Partner name</label><input v-model="partnerForm.name" class="form-control mb-3" required maxlength="150"><label class="form-label">Scopes</label><div v-for="s in scopeOptions" :key="s" class="form-check"><input :id="s" v-model="partnerForm.scopes" class="form-check-input" type="checkbox" :value="s"><label class="form-check-label" :for="s">{{s}}</label></div><label class="form-label mt-3">Allowed branches <span class="text-muted small">(leave empty for all branches)</span></label><div class="border rounded p-2 mb-3" style="max-height:120px;overflow:auto"><div v-for="b in branches" :key="b.id" class="form-check"><input :id="'partner-branch-'+b.id" v-model="partnerForm.branch_ids" class="form-check-input" type="checkbox" :value="b.id"><label class="form-check-label" :for="'partner-branch-'+b.id">{{b.name}}</label></div></div><label class="form-label mt-3">Requests per minute</label><input v-model.number="partnerForm.rate_limit_per_minute" type="number" min="1" max="600" class="form-control"></div><div class="modal-footer"><button type="button" class="btn btn-light" @click="showPartner=false">Cancel</button><button class="btn btn-success" :disabled="saving">Create client</button></div></form></div></div>
    <div v-if="newSecret" class="alert alert-warning mt-3"><strong>Copy this secret now. It will not be shown again.</strong><div class="d-flex gap-2 mt-2"><code class="text-break">{{newSecret}}</code><button class="btn btn-sm btn-outline-dark" @click="copySecret">Copy</button></div></div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import { Swal } from '../utils/swal';
const tab=ref('partners'),partners=ref([]),subscriptions=ref([]),notifications=ref([]),showPartner=ref(false),showSubscription=ref(false),saving=ref(false),newSecret=ref(''),plans=ref([]);
const scopeOptions=['catalog:read','orders:write','orders:read'];
const partnerForm=ref({name:'',scopes:['catalog:read','orders:write'],branch_ids:[],rate_limit_per_minute:60});
const subscriptionForm=ref({branch_id:'',plan_id:'',billing_cycle:'monthly',starts_on:new Date().toISOString().slice(0,10)});
const branches=ref([]);
const parse=v=>{try{return typeof v==='string'?JSON.parse(v):v||[]}catch{return[]}};
async function loadAll(){try{const [p,s,n,b,pl]=await Promise.all([api.get('/enterprise/partners'),api.get('/enterprise/subscriptions'),api.get('/notifications'),api.get('/branches'),api.get('/enterprise/subscription-plans')]);partners.value=p.data;subscriptions.value=s.data.data||[];notifications.value=n.data.notifications?.data||[];branches.value=b.data||[];plans.value=pl.data||[]}catch(e){console.error(e)}}
async function createPartner(){saving.value=true;try{const r=await api.post('/enterprise/partners',partnerForm.value);newSecret.value=r.data.client_secret;showPartner.value=false;partnerForm.value={name:'',scopes:['catalog:read','orders:write'],branch_ids:[],rate_limit_per_minute:60};await loadAll();Swal.fire({icon:'success',title:'Partner created',text:'Copy the secret shown below and store it securely.'})}finally{saving.value=false}}
async function assignSubscription(){await api.post('/enterprise/subscriptions',subscriptionForm.value);showSubscription.value=false;await loadAll();Swal.fire({icon:'success',title:'Subscription assigned'})}
async function generateInvoice(s){const r=await api.post(`/enterprise/subscriptions/${s.id}/invoices`);Swal.fire({icon:'success',title:'Invoice generated',text:`${r.data.invoice.invoice_number} — Rs ${r.data.invoice.amount}`})}
async function togglePartner(p){const status=p.status==='active'?'suspended':'active';await api.patch(`/enterprise/partners/${p.id}`,{status});await loadAll()}
async function readOne(n){await api.patch(`/notifications/${n.id}/read`);await loadAll()}
async function readAll(){await api.post('/notifications/read-all');await loadAll()}
async function copySecret(){await navigator.clipboard.writeText(newSecret.value);Swal.fire({icon:'success',title:'Copied'})}
onMounted(loadAll);
</script>
