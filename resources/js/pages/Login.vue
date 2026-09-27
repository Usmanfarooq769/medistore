<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3" style="background: linear-gradient(135deg, #0f5132, #198754 60%, #20c997)">
        <div class="card shadow-lg w-100" style="max-width: 400px; border-radius: 1rem">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="display-5 text-success"><i class="bi bi-capsule-pill"></i></div>
                    <h4 class="fw-bold mb-0">MediStore</h4>
                    <small class="text-muted">Medical Store Management System</small>
                </div>
                <form @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input v-model="form.email" type="email" class="form-control" required autofocus></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input v-model="form.password" :type="showPw ? 'text' : 'password'" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary" @click="showPw = !showPw"><i :class="['bi', showPw ? 'bi-eye-slash' : 'bi-eye']"></i></button></div>
                    </div>
                    <button class="btn btn-success w-100 btn-lg" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-box-arrow-in-right me-1"></i> Login
                    </button>
                </form>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-light flex-fill" @click="demo('admin')">Demo admin</button>
                    <button class="btn btn-sm btn-light flex-fill" @click="demo('cashier')">Demo cashier</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';
import { notify } from '../utils/swal';

const auth = useAuth();
const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '' });
const loading = ref(false);
const showPw = ref(false);

const demo = (r) => { form.email = `${r}@medistore.test`; form.password = 'password'; };

async function submit() {
    loading.value = true;
    try {
        const data = await auth.login(form);
        notify(data.message);
        router.push(route.query.redirect || (auth.isAdmin ? '/dashboard' : '/pos'));
    } catch (e) { /* shown */ } finally { loading.value = false; }
}
</script>
