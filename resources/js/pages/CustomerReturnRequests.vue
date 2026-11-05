<template>
  <section>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div><h4 class="mb-1">Customer Return Requests</h4><div class="text-muted small">Review customer requests, then process the stock/accounting return through Returns → New Customer Return.</div></div>
      <button class="btn btn-outline-secondary" @click="load" :disabled="busy"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-body border-bottom d-flex gap-2 align-items-center"><select v-model="status" class="form-select" style="max-width:220px" @change="load"><option value="">All statuses</option><option>pending</option><option>approved</option><option>rejected</option><option>completed</option></select><span class="small text-muted ms-auto">{{ rows.length }} shown</span></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Request</th><th>Customer / Invoice</th><th>Requested items</th><th>Reason</th><th>Status</th><th style="min-width:240px">Action</th></tr></thead><tbody>
        <tr v-for="r in rows" :key="r.id"><td><strong>{{r.request_no}}</strong><div class="small text-muted">{{date(r.created_at)}}</div></td><td>{{r.customer_name}}<div class="small text-muted">{{r.customer_phone}} · {{r.invoice_no}}</div></td><td><div v-for="(i,n) in r.items" :key="n" class="small">{{i.medicine_name}} × {{i.quantity}}</div></td><td>{{r.reason}}<div class="small text-muted">{{r.customer_note}}</div></td><td><span class="badge" :class="badge(r.status)">{{r.status}}</span><div class="small text-muted">{{r.staff_note}}</div></td><td>
          <template v-if="r.status==='pending'"><input v-model="notes[r.id]" class="form-control form-control-sm mb-2" placeholder="Staff note (optional)"><div class="d-flex gap-2"><button class="btn btn-sm btn-success" @click="review(r,'approve')">Approve</button><button class="btn btn-sm btn-outline-danger" @click="review(r,'reject')">Reject</button></div></template>
          <template v-else-if="r.status==='approved'"><router-link class="btn btn-sm btn-outline-primary mb-2" :to="`/returns/new?invoice=${encodeURIComponent(r.invoice_no)}`">Process actual return</router-link><div class="small text-muted mb-1">After saving the actual return transaction, enter its Return ID:</div><div class="input-group input-group-sm"><input v-model="returnIds[r.id]" class="form-control" type="number" min="1" placeholder="Sale return ID"><button class="btn btn-primary" @click="complete(r)">Link</button></div></template>
          <span v-else class="small text-muted">No action required</span>
        </td></tr>
        <tr v-if="!rows.length"><td colspan="6" class="text-center text-muted py-5">No customer return requests found.</td></tr>
      </tbody></table></div>
      <div class="card-footer bg-white d-flex justify-content-between"><button class="btn btn-sm btn-outline-secondary" :disabled="!prev" @click="page=prev;load()">Previous</button><button class="btn btn-sm btn-outline-secondary" :disabled="!next" @click="page=next;load()">Next</button></div>
    </div>
  </section>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import { Swal } from '../utils/swal';
const rows=ref([]),status=ref(''),busy=ref(false),page=ref(1),prev=ref(null),next=ref(null),notes=ref({}),returnIds=ref({});
const date=v=>v?new Date(v).toLocaleString():'—';
const badge=s=>({'pending':'text-bg-warning','approved':'text-bg-success','rejected':'text-bg-danger','completed':'text-bg-secondary'}[s]||'text-bg-light');
async function load(){busy.value=true;try{const r=await api.get('/customer-return-requests',{params:{status:status.value||undefined,page:page.value}});rows.value=r.data.data||[];prev.value=r.data.prev_page_url?r.data.current_page-1:null;next.value=r.data.next_page_url?r.data.current_page+1:null}catch{}finally{busy.value=false}}
async function review(r,decision){try{await api.post(`/customer-return-requests/${r.id}/review`,{decision,staff_note:notes.value[r.id]||null});await Swal.fire({icon:'success',title:'Updated',text:`Request ${decision}d.`});load()}catch{}}
async function complete(r){const id=Number(returnIds.value[r.id]);if(!id)return;try{await api.post(`/customer-return-requests/${r.id}/complete`,{sale_return_id:id});await Swal.fire({icon:'success',title:'Linked',text:'Actual return transaction linked.'});load()}catch{}}
onMounted(load);
</script>
