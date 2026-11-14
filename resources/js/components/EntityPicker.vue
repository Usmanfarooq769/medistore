<template>
    <div>
        <div v-if="modelValue" class="form-control d-flex justify-content-between align-items-center gap-2" style="min-height:38px">
            <span class="text-truncate"><i :class="['bi', cfg.icon, 'text-primary me-2']"></i>{{ shownLabel || `#${modelValue}` }}</span>
            <button type="button" class="btn btn-sm p-0 text-muted" aria-label="Clear selection" @click="clear"><i class="bi bi-x-lg"></i></button>
        </div>
        <Autocomplete v-else :url="cfg.url" :query-key="cfg.key" :params="cfg.params || {}" :icon="cfg.icon" :placeholder="placeholder || cfg.placeholder" empty-text="Nothing found" size="" @select="pick">
            <template #item="{ item }"><b>{{ cfg.label(item) }}</b> <small v-if="cfg.sub" class="text-muted ms-1">{{ cfg.sub(item) }}</small></template>
        </Autocomplete>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Autocomplete from './Autocomplete.vue';
import { money } from '../utils/format';

/**
 * Searchable replacement for "type the ID" inputs.
 *   <EntityPicker v-model="form.customer_id" type="customer" />
 * v-model stays the numeric id, so existing save logic does not change.
 */
const props = defineProps({ modelValue: { type: [Number, String, null], default: null }, type: { type: String, required: true }, placeholder: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);

const kinds = {
    customer: { url: '/customers', key: 'search', params: { per_page: 8 }, icon: 'bi-person', placeholder: 'Search customer name / phone', label: (i) => i.name, sub: (i) => i.phone },
    medicine: { url: '/medicines/search', key: 'q', icon: 'bi-capsule', placeholder: 'Search medicine name / generic', label: (i) => i.display_name || i.name, sub: (i) => i.generic_name },
    sale: { url: '/sales', key: 'search', params: { per_page: 8 }, icon: 'bi-receipt', placeholder: 'Search invoice no / customer', label: (i) => i.invoice_no, sub: (i) => `${i.customer_name || 'Walk-in'} · ${money(i.total)}` },
    prescription: { url: '/prescriptions', key: 'search', params: { per_page: 8 }, icon: 'bi-file-medical', placeholder: 'Search prescription no / patient', label: (i) => i.prescription_no, sub: (i) => i.customer?.name || i.doctor_name },
    batch: { url: '/alerts/expiry', key: 'search', params: { type: 'any' }, icon: 'bi-box-seam', placeholder: 'Search medicine or batch no', label: (i) => `${i.medicine?.name || 'Batch'} · ${i.batch_no || 'no batch'}`, sub: (i) => `exp ${i.expiry_date || '—'} · ${i.quantity} left` },
    claim: { url: '/stage4/insurance-claims', key: 'search', icon: 'bi-shield-check', placeholder: 'Search claim no', label: (i) => i.claim_no, sub: (i) => `${i.provider?.name || ''} ${i.status || ''}` },
};
const cfg = computed(() => kinds[props.type]);
const shownLabel = ref('');
function pick(item) { shownLabel.value = cfg.value.label(item); emit('update:modelValue', item.id); }
function clear() { shownLabel.value = ''; emit('update:modelValue', null); }
</script>
