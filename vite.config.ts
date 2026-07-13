import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/css/filament/admin/theme.css',
                'resources/js/pages/Games/TowerDefense/Playfield.vue',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: [
            // These 3 route files are locked on Windows (EPERM on realpath).
            // Redirect imports to fresh copies in routes_temp.
            // IMPORTANT: specific aliases must come BEFORE the catch-all @ alias.
            { find: '@/routes/assignments', replacement: fileURLToPath(new URL('./resources/js/routes_temp/routes/assignments', import.meta.url)) },
            { find: '@/routes/exams', replacement: fileURLToPath(new URL('./resources/js/routes_temp/routes/exams', import.meta.url)) },
            { find: '@/routes/profile', replacement: fileURLToPath(new URL('./resources/js/routes_temp/routes/profile', import.meta.url)) },
            { find: '@', replacement: fileURLToPath(new URL('./resources/js', import.meta.url)) },
        ]
    },
});
