import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api';

/** Low stock / expiry counts for the bell – server caches this, we poll every 2 min */
export const useAlerts = defineStore('alerts', () => {
    const counts = ref({ low_stock: 0, expiring: 0, expired: 0, low_items: [] });
    const loaded = ref(false);

    async function load() {
        if (document.hidden) return;
        try {
            const { data } = await api.get('/alerts/counts', { silent: true });
            counts.value = data;
            loaded.value = true;
        } catch (e) { /* ignore */ }
    }

    return { counts, loaded, load };
});
