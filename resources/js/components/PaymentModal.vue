<template>
    <Modal :show="show" :title="title" @close="$emit('close')">
        <form class="row g-3" @submit.prevent="save">
            <div class="col-12"><div class="alert alert-warning py-2 mb-0">{{ label }} <b>{{ name }}</b>: <b>{{ money(due) }}</b></div></div>
            <div class="col-md-6">
                <label class="form-label">Amount *</label>
                <div class="input-group">
                    <input v-model.number="form.amount" type="number" step="0.01" min="0.01" :max="due" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary" @click="form.amount = due">Full</button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Method</label>
                <select v-model="form.method" class="form-select">
                    <option v-for="m in methods" :key="m" :value="m" class="text-capitalize">{{ m }}</option>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Date</label><input v-model="form.payment_date" type="date" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Note</label><input v-model="form.note" class="form-control" placeholder="Cheque no / ref"></div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-light me-2" @click="$emit('close')">Cancel</button>
                <button type="submit" class="btn btn-warning" :disabled="saving">Save payment</button>
            </div>
        </form>
    </Modal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import api from '../api';
import { money, today } from '../utils/format';
import { notify } from '../utils/swal';
import Modal from './Modal.vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Payment' },
    label: { type: String, default: 'Due of' },
    endpoint: String,
    name: String,
    due: { type: Number, default: 0 },
    methods: { type: Array, default: () => ['cash', 'bank', 'cheque', 'online'] },
});
const emit = defineEmits(['close', 'saved']);
const saving = ref(false);
const form = reactive({ amount: 0, method: 'cash', payment_date: today(), note: '' });

watch(() => props.show, (v) => { if (v) Object.assign(form, { amount: props.due, method: props.methods[0], payment_date: today(), note: '' }); });

async function save() {
    saving.value = true;
    try {
        const { data } = await api.post(props.endpoint, { ...form });
        notify(data.message);
        emit('saved');
        emit('close');
    } catch (e) { /* shown */ } finally { saving.value = false; }
}
</script>
