import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '../api';
import { setCurrency } from '../utils/format';

export const useAuth = defineStore('auth', () => {
    const token = ref(localStorage.getItem('token'));
    const user = ref(null);
    const settings = ref({});

    const isAdmin = computed(() => user.value?.role === 'admin');
    const can = (key) => settings.value[key] === '1';

    function setSession(data) {
        if (data.token) {
            token.value = data.token;
            localStorage.setItem('token', data.token);
        }
        if (data.user) user.value = data.user;
        if (data.settings) {
            settings.value = data.settings;
            setCurrency(data.settings.currency);
        }
    }

    async function login(credentials) {
        const { data } = await api.post('/login', credentials);
        setSession(data);
        return data;
    }

    async function fetchMe() {
        const { data } = await api.get('/me', { silent: true });
        setSession(data);
    }

    function updateSettings(s) {
        settings.value = s;
        setCurrency(s.currency);
    }

    function clear() {
        token.value = null;
        user.value = null;
        localStorage.removeItem('token');
    }

    async function logout() {
        try { await api.post('/logout', {}, { silent: true }); } catch (e) { /* ignore */ }
        clear();
    }

    return { token, user, settings, isAdmin, can, login, fetchMe, logout, clear, updateSettings };
});
