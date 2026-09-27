<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="show" class="modal-backdrop-v" @mousedown.self="$emit('close')">
                <div class="modal-dialog w-100 m-0" :style="{ maxWidth: width }">
                    <div class="modal-content shadow">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ title }}</h5>
                            <button type="button" class="btn-close" @click="$emit('close')"></button>
                        </div>
                        <div class="modal-body"><slot /></div>
                        <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ show: Boolean, title: String, size: { type: String, default: '' } });
const emit = defineEmits(['close']);
const width = computed(() => ({ sm: '380px', lg: '800px', xl: '1140px' }[props.size] || '520px'));

const onKey = (e) => { if (e.key === 'Escape' && props.show) emit('close'); };
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>
