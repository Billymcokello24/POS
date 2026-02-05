import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        // Wayfinder disabled - causes build issues
        // process.env.VITE_SKIP_WAYFINDER !== 'true' && wayfinder({
        //     formVariants: true,
        // }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ].filter(Boolean),
    // Dev server config to support Vite HMR and cross-origin requests from the frontend
    server: {
        hmr: {
            host: 'localhost',
        },
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        headers: {
            'Access-Control-Allow-Origin': '*',
        },
    },
});
