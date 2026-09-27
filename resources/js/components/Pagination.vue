<template>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
        <div class="small text-muted">
            <template v-if="meta.total !== null && meta.total !== undefined">Showing {{ meta.from || 0 }}–{{ meta.to || 0 }} of {{ meta.total }}</template>
        </div>
        <ul class="pagination pagination-sm mb-0" v-if="meta.last_page > 1">
            <li class="page-item" :class="{ disabled: meta.current_page <= 1 }">
                <a class="page-link" href="#" @click.prevent="go(meta.current_page - 1)">&laquo;</a>
            </li>
            <li v-for="(p, i) in pages" :key="i" class="page-item" :class="{ active: p === meta.current_page, disabled: p === '…' }">
                <a class="page-link" href="#" @click.prevent="go(p)">{{ p }}</a>
            </li>
            <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }">
                <a class="page-link" href="#" @click.prevent="go(meta.current_page + 1)">&raquo;</a>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ meta: { type: Object, required: true } });
const emit = defineEmits(['change']);

const pages = computed(() => {
    const cur = props.meta.current_page, last = props.meta.last_page, out = [];
    const start = Math.max(1, cur - 2), end = Math.min(last, cur + 2);
    if (start > 1) { out.push(1); if (start > 2) out.push('…'); }
    for (let i = start; i <= end; i++) out.push(i);
    if (end < last) { if (end < last - 1) out.push('…'); out.push(last); }
    return out;
});

const go = (p) => {
    if (p === '…' || p < 1 || p > props.meta.last_page || p === props.meta.current_page) return;
    emit('change', p);
};
</script>
