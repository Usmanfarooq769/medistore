<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3 ms-login-bg">
        <div class="card custom-card shadow-lg w-100 overflow-hidden" style="max-width: 880px">
            <div class="row g-0">
                <div class="col-md-5 d-none d-md-flex flex-column justify-content-between p-5 text-white ms-login-side">
                    <div class="d-flex align-items-center gap-2"><span class="ms-logo-mark bg-white text-primary"><i class="bi bi-capsule-pill"></i></span><span class="fs-18 fw-semibold">MediStore</span></div>
                    <div>
                        <h3 class="fw-semibold mb-2 text-white">Run your pharmacy with confidence</h3>
                        <p class="mb-0 text-white-50">POS, stock, expiry alerts, purchases, returns and reports in one place.</p>
                    </div>
                    <div class="fs-12 text-white-50">Medical Store Management System</div>
                </div>
                <div class="col-md-7">
                    <div class="card-body p-4 p-md-5">
                        <h4 class="fw-semibold mb-1">Welcome back</h4>
                        <p class="text-muted mb-4">Sign in to continue</p>
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input v-model="form.email" type="email" class="form-control" required autofocus></div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input v-model="form.password" :type="showPw ? 'text' : 'password'" class="form-control" required>
                                    <button type="button" class="btn btn-light" @click="showPw = !showPw"><i :class="['bi', showPw ? 'bi-eye-slash' : 'bi-eye']"></i></button></div>
                            </div>
                            <button class="btn btn-primary w-100 btn-lg" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-box-arrow-in-right me-1"></i> Sign in
                            </button>
                        </form>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light flex-fill" @click="demo('admin')">Demo admin</button>
                            <button class="btn btn-sm btn-light flex-fill" @click="demo('cashier')">Demo cashier</button>
                        </div>
                    </div>
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
