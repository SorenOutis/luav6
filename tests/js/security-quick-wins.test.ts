import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { sanitizeRichHtml, sanitizeSvg } from '@/lib/sanitizeHtml';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('security quick wins', () => {
    it('upgrades Axios and removes the unused motion package', () => {
        const packageJson = JSON.parse(source('package.json')) as {
            dependencies: Record<string, string>;
        };

        expect(packageJson.dependencies.axios).toBe('^1.19.0');
        expect(packageJson.dependencies).not.toHaveProperty('motion');
    });

    it('sanitizes only HTML and SVG that cross explicit v-html boundaries', () => {
        const rich = sanitizeRichHtml(
            '<p style="color:red" onclick="alert(1)"><strong>Lesson</strong><script>alert(1)</script><img src=x onerror=alert(1)></p>',
        );
        expect(rich).toContain('<strong>Lesson</strong>');
        expect(rich).not.toContain('<script');
        expect(rich).not.toContain('onclick');
        expect(rich).not.toContain('onerror');
        expect(rich).not.toContain('style=');

        const svg = sanitizeSvg(
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10" /></svg>',
        );
        expect(svg).toContain('<svg');
        expect(svg).not.toContain('onload');
    });

    it('uses the sanitizers at every intentional rich-content sink', () => {
        const lesson = source('resources/js/pages/Courses/Lesson.vue');
        const twoFactor = source(
            'resources/js/components/TwoFactorSetupModal.vue',
        );

        expect(lesson).toContain('sanitizeRichHtml');
        expect(lesson).toContain('v-html="sanitizedContent"');
        expect(twoFactor).toContain('sanitizeSvg');
        expect(twoFactor).toContain('v-html="sanitizedQrCodeSvg"');
    });

    it('bounds both chat composers in the browser', () => {
        const chats = source('resources/js/pages/Chats.vue');
        const widget = source('resources/js/components/FloatingWidget.vue');

        expect(chats).toContain('const MAX_MESSAGE_CHARACTERS = 8000');
        expect(chats).toContain(':maxlength="MAX_MESSAGE_CHARACTERS"');
        expect(widget).toContain('const MAX_MESSAGE_CHARACTERS = 8000');
        expect(widget).toContain(':maxlength="MAX_MESSAGE_CHARACTERS"');
    });

    it('removes the global regex sanitizer from every middleware stack', () => {
        const bootstrap = source('bootstrap/app.php');
        const filament = source(
            'app/Providers/Filament/AdminPanelProvider.php',
        );

        expect(bootstrap).not.toContain('SanitizeInput');
        expect(filament).not.toContain('SanitizeInput');
    });
});
