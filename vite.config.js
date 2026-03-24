import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/foodo.css',
                'resources/js/foodo/charts.js',
                'resources/js/foodo/chat.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        chunkSizeWarningLimit: 200,
        rollupOptions: {
            external: [
                '@ledgerhq/hw-transport-webusb',
                '@ledgerhq/hw-app-eth',
                '@trezor/connect-web',
            ],
            output: {
                manualChunks(id) {
                    // Split chart libraries into separate chunk
                    if (id.includes('chart.js') || id.includes('chartjs')) {
                        return 'vendor-charts';
                    }
                },
            },
        },
    },
});
