<template>
    <Teleport to="body">
        <transition name="modal-fade">
            <div
                v-if="show"
                class="modal-backdrop-v"
                :data-bs-theme="theme"
                @mousedown.self="$emit('close')"
            >
                <div
                    class="modal-dialog"
                    :style="{ maxWidth: width }"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                >
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ title }}</h5>

                            <button
                                type="button"
                                class="btn-close"
                                aria-label="Close"
                                @click="$emit('close')"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <slot></slot>
                        </div>

                        <div
                            v-if="$slots.footer"
                            class="modal-footer"
                        >
                            <slot name="footer"></slot>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script>
// Shared between all modal instances (supports stacked modals)
let lockCount = 0;
</script>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    size: { type: String, default: '' }
});

const emit = defineEmits(['close']);

const width = computed(() => ({
    sm: '380px',
    lg: '800px',
    xl: '1140px'
}[props.size] || '520px'));

/* ---------- Theme (Teleport moves the modal out of the app container,
   so we copy the active data-bs-theme onto the modal itself) ---------- */
const theme = ref('light');
let observer = null;

const detectTheme = () => {
    const el = document.querySelector('[data-bs-theme]:not(.modal-backdrop-v)');
    theme.value = el?.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
};

/* ---------- Body scroll lock (counter-based) ---------- */
let locked = false;

const setLock = (value) => {
    if (value && !locked) {
        locked = true;
        lockCount++;
    } else if (!value && locked) {
        locked = false;
        lockCount--;
    }
    document.body.classList.toggle('modal-open', lockCount > 0);
};

/* ---------- Escape key ---------- */
const onKey = (e) => {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
};

watch(
    () => props.show,
    (visible) => {
        if (visible) detectTheme();
        setLock(visible);
    },
    { immediate: true }
);

onMounted(() => {
    detectTheme();
    window.addEventListener('keydown', onKey);

    observer = new MutationObserver(detectTheme);
    observer.observe(document.documentElement, {
        attributes: true,
        subtree: true,
        attributeFilter: ['data-bs-theme']
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    observer?.disconnect();
    setLock(false);
});
</script>

<style>
/* ===== Backdrop + theme variables ===== */
.modal-backdrop-v {
    /* Bootstrap defines these only on .modal, so we define them here */
    --bs-modal-bg: var(--bs-body-bg);
    --bs-modal-color: var(--bs-body-color);
    --bs-modal-border-color: var(--bs-border-color);

    position: fixed;
    inset: 0;
    z-index: 1055;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 24px;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(2px);
}

.modal-backdrop-v[data-bs-theme="dark"] {
    background: rgba(0, 0, 0, 0.7);
}

/* ===== Dialog ===== */
.modal-backdrop-v .modal-dialog {
    flex: 0 1 auto;
    width: 100%;
    margin: auto;
    pointer-events: auto;
}

.modal-backdrop-v .modal-content {
    background-color: var(--bs-modal-bg);
    color: var(--bs-modal-color);
    border: 1px solid var(--bs-modal-border-color);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
}

/* ===== Header ===== */
.modal-backdrop-v .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px 24px;
    background-color: var(--bs-modal-bg);
    border-bottom: 1px solid var(--bs-border-color);
}

.modal-backdrop-v .modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.4;
    color: var(--bs-body-color);
}

.modal-backdrop-v .modal-header .btn-close {
    flex-shrink: 0;
    margin: 0 0 0 16px;
}

[data-bs-theme="dark"].modal-backdrop-v .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* ===== Body ===== */
.modal-backdrop-v .modal-body {
    padding: 24px;
    color: var(--bs-body-color);
}

.modal-backdrop-v .form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--bs-body-color);
}

.modal-backdrop-v .form-control,
.modal-backdrop-v .form-select {
    min-height: 44px;
    padding: 10px 12px;
    border-radius: 8px;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-border-color);
}

.modal-backdrop-v .form-control:focus,
.modal-backdrop-v .form-select:focus {
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: rgba(var(--bs-primary-rgb), 0.6);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
}

.modal-backdrop-v .form-control::placeholder {
    color: var(--bs-secondary-color);
}

.modal-backdrop-v .form-control:disabled,
.modal-backdrop-v .form-select:disabled {
    background-color: var(--bs-secondary-bg);
}

/* Textareas shouldn't be forced to 44px only */
.modal-backdrop-v textarea.form-control {
    min-height: 88px;
}

/* ===== Footer ===== */
.modal-backdrop-v .modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px 22px;
    background-color: var(--bs-modal-bg);
    border-top: 1px solid var(--bs-border-color);
}

.modal-backdrop-v .modal-footer .btn,
.modal-backdrop-v .modal-body > form > .text-end .btn {
    min-height: 42px;
    padding: 9px 18px;
    border-radius: 8px;
    font-weight: 500;
}

/* ===== Scroll lock ===== */
body.modal-open {
    overflow: hidden;
}

/* ===== Transition ===== */
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

/* ===== Mobile ===== */
@media (max-width: 575.98px) {
    .modal-backdrop-v {
        padding: 12px;
    }

    .modal-backdrop-v .modal-header {
        padding: 18px;
    }

    .modal-backdrop-v .modal-body {
        padding: 18px;
    }

    .modal-backdrop-v .modal-footer {
        padding: 14px 18px 18px;
    }
}
</style>