<template>
    <div class="dt-wrap">
        <div v-if="loading" class="dt-progress" role="progressbar" aria-label="Loading"></div>

        <div v-if="exportable && rows.length && auth.perm('export.data')" class="dt-tools no-print">
            <div class="btn-group btn-group-sm" role="group" aria-label="Export">
                <button type="button" class="btn btn-light" title="Download as Excel" :disabled="exporting" @click="doExport('xlsx')"><i class="bi bi-file-earmark-excel text-success"></i> Excel</button>
                <button type="button" class="btn btn-light" title="Download as PDF" :disabled="exporting" @click="doExport('pdf')"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</button>
                <button type="button" class="btn btn-light" title="Download as CSV" :disabled="exporting" @click="doExport('csv')"><i class="bi bi-filetype-csv"></i> CSV</button>
            </div>
        </div>

        <div ref="wrapEl" class="table-responsive">
            <table class="table table-hover data-table mb-0" :class="tableClass">
                <thead class="dt-head"><slot name="head" :sort="sort" :sortBy="sortBy" :icon="sortIcon" /></thead>
                <tbody :class="{ 'is-reloading': loading && rows.length }">
                    <!-- First load: skeleton rows -->
                    <template v-if="loading && !rows.length">
                        <tr v-for="i in 6" :key="'s' + i">
                            <td v-for="c in cols" :key="c"><div class="skeleton" :style="{ width: skeletonWidth(i, c) }"></div></td>
                        </tr>
                    </template>

                    <!-- Error -->
                    <tr v-else-if="failed">
                        <td :colspan="cols" class="p-0">
                            <div class="state-box">
                                <div class="state-icon error"><i class="bi bi-wifi-off"></i></div>
                                <div class="fw-semibold text-body mb-1">Couldn't load the data</div>
                                <div class="small mb-3">Check your connection and try again.</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="load()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Try again
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty -->
                    <tr v-else-if="!rows.length">
                        <td :colspan="cols" class="p-0">
                            <div class="state-box">
                                <div class="state-icon"><i class="bi bi-inbox"></i></div>
                                <div class="fw-semibold text-body">{{ emptyText }}</div>
                            </div>
                        </td>
                    </tr>

                    <!-- Rows -->
                    <template v-else>
                        <template v-for="(item, index) in rows" :key="item.id ?? index">
                            <slot name="row" :item="item" :index="index" />
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="meta && showPager" class="card-footer">
        <Pagination :meta="meta" @change="load" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api, { isCancel } from '../api';
import Pagination from './Pagination.vue';
import { useAuth } from '../stores/auth';
import { exportCsv, exportPdf, exportXlsx, tableToMatrix } from '../utils/exporter';

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
    exportable: { type: Boolean, default: true },
});
const auth = useAuth();
const wrapEl = ref(null);
const exporting = ref(false);
async function doExport(kind) {
    const m = tableToMatrix(wrapEl.value || document);
    if (m.length < 2) return;
    exporting.value = true;
    const title = (document.title || 'Export').split(' | ')[0];
    try { if (kind === 'xlsx') await exportXlsx(m, title); else if (kind === 'pdf') await exportPdf(m, title, auth.settings.store_name); else exportCsv(m, title); }
    finally { exporting.value = false; }
}
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

// deterministic "random" widths so skeleton rows look natural but never jump
const skeletonWidth = (row, col) => `${45 + ((row * 7 + col * 13) % 45)}%`;

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