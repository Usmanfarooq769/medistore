<template>
    <div class="position-relative">
        <div class="input-group" :class="size ? `input-group-${size}` : ''">
            <span class="input-group-text"><i :class="['bi', icon]"></i></span>
            <input ref="input" v-model="q" type="text" class="form-control" :placeholder="placeholder" autocomplete="off"
                   @input="search" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                   @keydown.enter.prevent="choose(active)" @keydown.esc="open = false" @focus="open = items.length > 0" @blur="close">
            <button v-if="q" class="btn btn-outline-secondary" type="button" @mousedown.prevent="clear"><i class="bi bi-x"></i></button>
        </div>
        <div v-if="open" class="ac-box">
            <div v-for="(item, i) in items" :key="item.id" class="ac-item" :class="{ active: i === active }" @mousedown.prevent="choose(i)" @mouseenter="active = i">
                <slot name="item" :item="item">{{ item.name }}</slot>
            </div>
            <div v-if="!items.length && !loading" class="p-2 text-muted small">{{ emptyText }}</div>
            <div v-if="loading" class="p-2 text-muted small"><span class="spinner-border spinner-border-sm"></span> Searching…</div>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import api, { isCancel } from '../api';
import { debounce } from '../utils/format';

/** Debounced AJAX autocomplete with keyboard support; barcode exact match auto-selects */
const props = defineProps({
    url: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
    queryKey: { type: String, default: 'q' },
    placeholder: { type: String, default: 'Search…' },
    icon: { type: String, default: 'bi-search' },
    size: { type: String, default: '' },
    emptyText: { type: String, default: 'No results' },
    clearOnSelect: { type: Boolean, default: true },
    autofocus: Boolean,
});
const emit = defineEmits(['select']);

const q = ref('');
const items = ref([]);
const active = ref(0);
const open = ref(false);
const loading = ref(false);
const input = ref(null);
let controller = null;

const search = debounce(async () => {
    const term = q.value.trim();
    if (!term) { items.value = []; open.value = false; return; }
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    open.value = true;
    try {
        const { data } = await api.get(props.url, { params: { ...props.params, [props.queryKey]: term }, signal: controller.signal, silent: true });
        items.value = data.data || data;
        active.value = 0;
        if (items.value.length === 1 && items.value[0].barcode && items.value[0].barcode === term) choose(0);
    } catch (e) {
        if (!isCancel(e)) items.value = [];
    } finally {
        loading.value = false;
    }
}, 250);

function move(d) { active.value = Math.max(0, Math.min(items.value.length - 1, active.value + d)); }
function choose(i) {
    const item = items.value[i];
    if (!item) return;
    emit('select', item);
    open.value = false;
    if (props.clearOnSelect) { q.value = ''; items.value = []; }
}
function clear() { q.value = ''; items.value = []; open.value = false; input.value?.focus(); }
function close() { setTimeout(() => { open.value = false; }, 150); }

onMounted(() => { if (props.autofocus) input.value?.focus(); });
onBeforeUnmount(() => controller?.abort());
defineExpose({ focus: () => input.value?.focus(), setText: (t) => { q.value = t; }, clear });
</script>
