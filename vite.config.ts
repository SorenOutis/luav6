import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import { execSync } from 'node:child_process';

/**
 * Resolve the PHP binary path.
 * Tries `php` first (works when in PATH, e.g. via Laravel Herd),
 * then falls back to common install locations.
 */
function resolvePhpBinary(): string {
    try {
        execSync('php -v', { stdio: 'ignore' });
        return 'php';
    } catch {
        // Windows — Laravel Herd
        const herdPath = `${process.env.USERPROFILE || ''}\\.config\\herd\\bin\\php.bat`;
        try {
            execSync(`"${herdPath}" -v`, { stdio: 'ignore' });
            return herdPath;
        } catch {
            // macOS — Homebrew
            const brewPaths = [
                '/opt/homebrew/bin/php',
                '/usr/local/bin/php',
            ];
            for (const p of brewPaths) {
                try {
                    execSync(`${p} -v`, { stdio: 'ignore' });
                    return p;
                } catch { /* skip */ }
            }
            // Fallback — hope it's in PATH
            return 'php';
        }
    }
}

const phpBinary = resolvePhpBinary();
const wayfinderCommand = `${phpBinary} artisan wayfinder:generate`;

export default defineConfig({
    plugins: [
        wayfinder({
            command: wayfinderCommand,
        }),
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/css/filament/admin/theme.css',
                // Pages with their own dynamic imports (e.g. pixi.js) need explicit
                // entries so Vite's manifest keeps their source path for @vite() lookups.
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
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url))
        }
    }
});
