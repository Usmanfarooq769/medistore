<template>
    <div class="table-responsive">
        <table class="table table-hover mb-0" :class="tableClass">
            <thead class="table-light"><slot name="head" :sort="sort" :sortBy="sortBy" :icon="sortIcon" /></thead>
            <tbody :style="loading && rows.length ? 'opacity:.5' : ''">
                <template v-if="loading && !rows.length">
                    <tr v-for="i in 5" :key="'s' + i"><td :colspan="cols"><div class="skeleton"></div></td></tr>
                </template>
                <tr v-else-if="failed">
                    <td :colspan="cols" class="text-center text-danger py-4">Failed to load. <a href="#" @click.prevent="load()">Retry</a></td>
                </tr>
                <tr v-else-if="!rows.length">
                    <td :colspan="cols" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>{{ emptyText }}</td>
                </tr>
                <template v-else>
                    <template v-for="(item, index) in rows" :key="item.id ?? index">
                        <slot name="row" :item="item" :index="index" />
                    </template>
                </template>
            </tbody>
        </table>
    </div>
    <div v-if="meta && showPager" class="card-footer">
        <Pagination :meta="meta" @change="load" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api, { isCancel } from '../api';
import Pagination from './Pagination.vue';

/**
 * Server-side paginated AJAX table.
 * - debounced reload when filters change (300ms)
 * - previous request cancelled (AbortController) => no wasted server work
 */
const props = defineProps({
    url: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    cols: { type: Number, default: 6 },
    perPage: { type: Number, default: 15 },
    emptyText: { type: String, default: 'No records found.' },
    tableClass: { type: String, default: '' },
    showPager: { type: Boolean, default: true },
    defaultSort: { type: String, default: null },
});
const emit = defineEmits(['loaded']);

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const failed = ref(false);
const page = ref(1);
const sort = ref({ key: props.defaultSort, dir: 'asc' });
let controller = null;
let timer = null;

const clean = (o) => Object.fromEntries(Object.entries(o || {}).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false));

function normalize(d) {
    const m = d.meta || d;
    const hasNext = !!(d.links?.next || d.next_page_url);
    return {
        current_page: m.current_page || 1,
        last_page: m.last_page ?? (hasNext ? (m.current_page || 1) + 1 : m.current_page || 1),
        from: m.from, to: m.to, total: m.total ?? null,
    };
}

async function load(p) {
    if (p) page.value = p;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    failed.value = false;
    try {
        const params = { page: page.value, per_page: props.perPage, ...clean(props.filters) };
        if (sort.value.key) { params.sort = sort.value.key; params.dir = sort.value.dir; }
        const { data } = await api.get(props.url, { params, signal: controller.signal, silent: true });
        rows.value = data.data || [];
        meta.value = normalize(data);
        emit('loaded', data);
    } catch (e) {
        if (!isCancel(e)) failed.value = true;
    } finally {
        loading.value = false;
    }
}

function sortBy(key) {
    sort.value = { key, dir: sort.value.key === key && sort.value.dir === 'asc' ? 'desc' : 'asc' };
    load(1);
}
const sortIcon = (key) => (sort.value.key !== key ? 'bi-chevron-expand opacity-25' : sort.value.dir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill');

watch(() => JSON.stringify(props.filters), () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch(() => props.url, () => load(1));

onMounted(() => load(1));
onBeforeUnmount(() => { controller?.abort(); clearTimeout(timer); });

defineExpose({ reload: () => load(), load, rows });
</script>
