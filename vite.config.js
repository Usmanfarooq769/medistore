import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js'], refresh: true }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
    resolve: { alias: { '@': '/resources/js' } },
    build: {
        chunkSizeWarningLimit: 900,
        rollupOptions: {
            output: {
                // vendor libraries in separate cacheable chunks
                manualChunks: { vue: ['vue', 'vue-router', 'pinia'], ui: ['sweetalert2', 'axios'], chart: ['chart.js'] },
            },
        },
    },
});
