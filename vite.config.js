import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/filament/admin/theme.css',
                    // Their own entries so three.js loads only when a 3D view is opened (ADR 0049).
                    'Modules/Class/resources/js/session-scene.js',
                    'Modules/Campus/resources/js/building-scene.js',
                    'Modules/Campus/resources/js/organization-scene.js',
                    'Modules/Campus/resources/js/role-scene.js',
                    'Modules/Campus/resources/js/staff-scene.js',
                ],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                // `@/Layouts/AppLayout.vue` from anywhere, including module pages.
                '@': path.resolve('resources/js'),
                'ziggy-js': path.resolve('vendor/tightenco/ziggy'),
            },
        },
        // Keeps the exports of entries imported at run time (session-scene.js).
        build: {
            rollupOptions: {
                preserveEntrySignatures: 'exports-only',
            },
        },
        // The dev server runs inside the Docker container; the browser reaches it
        // through the published VITE_PORT (5174, so it doesn't clash with ../LMS).
        server: {
            host: '0.0.0.0',
            port: Number(env.VITE_PORT || 5174),
            strictPort: true,
            hmr: {
                host: 'localhost',
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
