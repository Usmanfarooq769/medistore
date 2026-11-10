import { createApp } from 'vue';
import { createPinia } from 'pinia';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/app.css';
import '../css/rixzo-bridge.css';

import App from './App.vue';
import router from './router';

const app = createApp(App);
app.use(createPinia());
app.use(router);

// mount after first navigation (auth check) => no flicker
router.isReady().then(() => app.mount('#app'));

// Installable mobile web app shell. Authenticated API responses are never cached.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
