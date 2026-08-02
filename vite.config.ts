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
            // Wayfinder output is generated into routes_temp/ by the prebuild
            // script (bin/generate-routes.mjs) because the canonical
            // resources/js/{routes,actions,wayfinder} copies are gitignored and
            // can be locked on Windows (EPERM from a crashed wayfinder process).
            // Redirect ALL generated imports there so fresh clones build and
            // type-check out of the box.
            // IMPORTANT: specific aliases must come BEFORE the catch-all @ alias.
            {
                find: '@/routes',
                replacement: fileURLToPath(
                    new URL(
                        './resources/js/routes_temp/routes',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@/actions',
                replacement: fileURLToPath(
                    new URL(
                        './resources/js/routes_temp/actions',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@/wayfinder',
                replacement: fileURLToPath(
                    new URL(
                        './resources/js/routes_temp/wayfinder',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@',
                replacement: fileURLToPath(
                    new URL('./resources/js', import.meta.url),
                ),
            },
        ],
    },
});
