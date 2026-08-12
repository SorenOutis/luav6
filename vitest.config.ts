import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: [
            {
                find: '@/routes/exams',
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/routes/exams.ts',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@motionone/vue',
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/motionone-vue.ts',
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
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.ts'],
        globals: true,
    },
});
