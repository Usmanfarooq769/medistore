<template>
  <div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4"><div><h4 class="mb-1">MediStore Product Roadmap</h4><div class="text-muted small">Track delivered releases, current work and what remains before product completion.</div></div><button class="btn btn-outline-secondary btn-sm" @click="load"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="text-muted small">Overall roadmap completion</div><div class="display-5 fw-bold">{{ summary.overall_progress ?? 0 }}%</div><div class="small text-muted">{{ summary.completed_milestones || 0 }} of {{ summary.total_milestones || 0 }} releases marked complete</div></div><div class="text-end"><div class="text-muted small">Remaining releases</div><div class="fs-2 fw-bold">{{ summary.remaining_milestones ?? 0 }}</div><div class="small text-muted">Includes current release</div></div></div><div class="progress mt-3" style="height:12px"><div class="progress-bar bg-success" :style="{width:(summary.overall_progress||0)+'%'}"></div></div><div v-if="summary.active_milestone" class="alert alert-info py-2 mt-3 mb-0"><strong>Current focus:</strong> {{ summary.active_milestone.title }}</div></div></div>
    <div class="row g-3">
      <div v-for="m in milestones" :key="m.id" class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="small text-muted">Release {{ String(m.milestone_number).padStart(2,'0') }}</div><h5 class="mb-1">{{ m.title }}</h5><p class="small text-muted mb-2">{{ m.description }}</p></div><span class="badge" :class="statusClass(m.status)">{{ label(m.status) }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span>Progress</span><strong>{{ m.progress_percent }}%</strong></div><div class="progress mb-3" style="height:8px"><div class="progress-bar" :class="m.status==='completed'?'bg-success':'bg-primary'" :style="{width:m.progress_percent+'%'}"></div></div>
        <div v-if="m.tasks?.length" class="border-top pt-2"><div class="small fw-semibold mb-2">Work items</div><div v-for="t in m.tasks" :key="t.id" class="d-flex align-items-center gap-2 py-1"><span class="flex-grow-1 small">{{ t.title }}</span><select v-if="canManage" :value="t.status" class="form-select form-select-sm" style="max-width:125px" @change="updateTask(t,$event.target.value)"><option value="planned">Planned</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="completed">Completed</option></select><span v-else class="badge bg-light text-dark">{{ label(t.status) }}</span></div></div>
        <div v-if="canManage" class="border-top mt-3 pt-3 d-flex flex-wrap align-items-center gap-2"><label class="small text-muted">Milestone status</label><select :value="m.status" class="form-select form-select-sm" style="max-width:150px" @change="updateMilestone(m,{status:$event.target.value,progress_percent:m.progress_percent})"><option value="planned">Planned</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="completed">Completed</option></select><label class="small text-muted ms-2">Progress</label><input type="number" min="0" max="100" :value="m.progress_percent" class="form-control form-control-sm" style="max-width:80px" @change="updateMilestone(m,{status:m.status,progress_percent:Number($event.target.value)})"><span class="small text-muted">%</span></div>
      </div></div></div>
    </div>
    <div class="alert alert-warning mt-4 mb-0"><strong>Completion definition:</strong> This is an implementation roadmap, not a regulatory certification or proof of production readiness. Releases 1–6 are marked complete based on your confirmation. Release 7 remains in progress until its workflows are tested and signed off. Releases 8–9 remain future work.</div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../api';
import { useAuth } from '../stores/auth';
const auth=useAuth(), canManage=computed(()=>auth.isAdmin), summary=ref({}), milestones=ref([]);
const label=v=>(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
const statusClass=v=>v==='completed'?'bg-success':v==='in_progress'?'bg-primary':v==='blocked'?'bg-danger':'bg-secondary';
async function load(){const {data}=await api.get('/product-roadmap');summary.value=data.summary;milestones.value=data.milestones;}
async function updateMilestone(m,payload){await api.patch(`/product-roadmap/milestones/${m.id}`,payload);await load();}
async function updateTask(t,status){await api.patch(`/product-roadmap/tasks/${t.id}`,{status});await load();}
onMounted(load);
</script>
