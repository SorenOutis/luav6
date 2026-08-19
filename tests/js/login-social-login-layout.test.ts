import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('login social sign-in layout', () => {
    it('keeps social buttons in the same single-column flow as the credentials', () => {
        const login = source('resources/js/pages/auth/Login.vue');

        expect(login).toContain('defineOptions({ layout: AuthCard });');
        expect(login).toContain('<div class="grid gap-6">');
        expect(login).not.toContain('wide: true');
        expect(login).not.toContain('lg:grid-cols-2');
    });

    it('labels the social sign-in section above the provider buttons', () => {
        const login = source('resources/js/pages/auth/Login.vue');
        const heading = login.indexOf('id="social-login-heading"');
        const buttons = login.indexOf('<SocialAuthButtons');

        expect(login).toContain('aria-labelledby="social-login-heading"');
        expect(login).toContain('Social Login');
        expect(login).toContain('stacked');
        expect(login).toContain('hide-divider');
        expect(heading).toBeGreaterThan(-1);
        expect(buttons).toBeGreaterThan(heading);
    });
});
