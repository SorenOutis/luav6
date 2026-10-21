import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: [
            {
                find: '@/routes/profile',
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/routes/profile.ts',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: /^@\/routes$/,
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/routes/index.ts',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@/routes/verification',
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/routes/verification.ts',
                        import.meta.url,
                    ),
                ),
            },
            {
                find: '@/actions/App/Http/Controllers/Settings/ProfileController',
                replacement: fileURLToPath(
                    new URL(
                        './tests/js/stubs/actions/ProfileController.ts',
                        import.meta.url,
                    ),
                ),
            },
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
        setupFiles: ['./tests/js/setup.ts'],
        globals: true,
        // Several suites mount a full page (Exams/Show, the dashboard shell)
        // with GSAP running. The 5s default is tight for them on a loaded CI
        // runner, where a slow mount trips the timeout and fails a suite
        // that passes in isolation.
        testTimeout: 15000,
    },
});
