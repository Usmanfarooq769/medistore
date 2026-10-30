<template>
    <div>
        <slot name="top" />

        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1">
                    <div class="input-group" style="max-width: 340px">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input v-model="filters.search" type="search" class="form-control" :placeholder="searchPlaceholder" :aria-label="searchPlaceholder">
                    </div>
                    <slot name="filters" :filters="filters" />
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span v-if="total !== null" class="result-count d-none d-sm-inline">{{ total }} {{ total === 1 ? 'record' : 'records' }}</span>
                    <button class="btn btn-success d-inline-flex align-items-center" @click="openForm()">
                        <i class="bi bi-plus-lg me-2"></i>{{ addLabel }}
                    </button>
                </div>
            </div>

            <DataTable ref="table" :url="endpoint" :filters="filters" :cols="columns.length + 1" @loaded="onLoaded">
                <template #head>
                    <tr>
                        <th v-for="c in columns" :key="c.key">{{ c.label }}</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </template>

                <template #row="{ item }">
                    <tr>
                        <td v-for="c in columns" :key="c.key" :class="c.class">
                            <slot :name="`cell-${c.key}`" :item="item">{{ c.format ? c.format(item[c.key], item) : (item[c.key] ?? '—') }}</slot>
                        </td>
                        <td class="text-end text-nowrap">
                            <slot name="actions" :item="item" :reload="reload" />
                            <button class="btn btn-icon btn-soft-primary ms-1" title="Edit" aria-label="Edit" @click="openForm(item)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button v-if="canDelete" class="btn btn-icon btn-soft-danger ms-1" title="Delete" aria-label="Delete" @click="remove(item)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>

        <Modal :show="show" :title="(editing ? 'Edit ' : 'Add ') + singular" :size="modalSize" @close="show = false">
            <form :id="formId" class="row g-3" autocomplete="off" @submit.prevent="save">
                <div v-for="f in fields" :key="f.key" :class="f.col || 'col-12'">
                    <template v-if="f.type === 'switch'">
                        <div class="form-check form-switch mt-2">
                            <input :id="'f_' + f.key" v-model="form[f.key]" class="form-check-input" type="checkbox" role="switch">
                            <label class="form-check-label" :for="'f_' + f.key">{{ f.label }}</label>
                        </div>
                    </template>

                    <template v-else>
                        <label class="form-label" :for="'f_' + f.key">{{ f.label }} <span v-if="f.required" class="text-danger">*</span></label>

                        <textarea v-if="f.type === 'textarea'" :id="'f_' + f.key" v-model="form[f.key]" class="form-control" rows="3" :placeholder="f.placeholder"></textarea>

                        <select v-else-if="f.type === 'select'" :id="'f_' + f.key" v-model="form[f.key]" class="form-select" :required="f.required">
                            <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>

                        <input v-else :id="'f_' + f.key" v-model="form[f.key]" :type="f.type || 'text'" class="form-control"
                               :required="f.required && !(f.type === 'password' && editing)"
                               :step="f.step" :min="f.min"
                               :placeholder="f.type === 'password' && editing ? 'Leave blank to keep current password' : f.placeholder">

                        <div v-if="f.hint" class="form-text">{{ f.hint }}</div>
                    </template>
                </div>
            </form>

            <template #footer>
                <button type="button" class="btn btn-outline-secondary" @click="show = false">Cancel</button>
                <button type="submit" class="btn btn-success d-inline-flex align-items-center" :form="formId" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                    {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Save' }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import api from '../api';
import { confirm, notify } from '../utils/swal';
import DataTable from './DataTable.vue';
import Modal from './Modal.vue';

/** Generic list + add/edit modal + delete, driven by field/column config */
const props = defineProps({
    endpoint: { type: String, required: true },
    singular: { type: String, required: true },
    fields: { type: Array, required: true },
    columns: { type: Array, required: true },
    defaults: { type: Object, default: () => ({}) },
    searchPlaceholder: { type: String, default: 'Search…' },
    addLabel: { type: String, default: 'Add' },
    canDelete: { type: Boolean, default: true },
    deleteText: { type: String, default: 'This record will be deleted.' },
    modalSize: { type: String, default: '' },
    initialFilters: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['saved']);

const formId = `crud-form-${Math.random().toString(36).slice(2, 8)}`;
const table = ref(null);
const filters = reactive({ search: '', ...props.initialFilters });
const show = ref(false);
const editing = ref(null);
const saving = ref(false);
const total = ref(null);
const form = reactive({});

const onLoaded = (d) => { total.value = d.meta?.total ?? d.total ?? null; };

function openForm(item = null) {
    editing.value = item;
    Object.keys(form).forEach((k) => delete form[k]);
    props.fields.forEach((f) => { form[f.key] = item ? (f.type === 'password' ? '' : item[f.key] ?? '') : (props.defaults[f.key] ?? (f.type === 'switch' ? true : '')); });
    show.value = true;
}

async function save() {
    saving.value = true;
    try {
        const { data } = editing.value
            ? await api.put(`${props.endpoint}/${editing.value.id}`, { ...form })
            : await api.post(props.endpoint, { ...form });
        notify(data.message || 'Saved');
        show.value = false;
        table.value.reload();
        emit('saved', data);
    } catch (e) { /* shown by interceptor */ } finally {
        saving.value = false;
    }
}

async function remove(item) {
    if (!(await confirm({ text: `${item.name ?? ''} – ${props.deleteText}`, confirmText: 'Yes, delete' }))) return;
    try {
        const { data } = await api.delete(`${props.endpoint}/${item.id}`);
        notify(data.message || 'Deleted');
        table.value.reload();
    } catch (e) { /* shown */ }
}

const reload = () => table.value?.reload();
defineExpose({ reload, openForm });
</script>