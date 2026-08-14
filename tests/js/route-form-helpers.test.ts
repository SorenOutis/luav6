import { describe, expect, it } from 'vitest';
import { formPropsFor, withForm } from '@/lib/route-helpers';

/**
 * Regression cover for the bug that stopped profile pictures from saving.
 *
 * The profile form is multipart (it carries a file), and PHP only parses a
 * multipart body into $_POST/$_FILES for POST requests. Emitting the route's
 * real verb (PATCH) meant Laravel saw no file at all and the upload was
 * dropped with no error. Form attributes must therefore always be POST, with
 * the real verb carried in `_method`.
 */
describe('formPropsFor', () => {
    it('spoofs PATCH as POST with _method so multipart uploads are parsed', () => {
        const route = () => ({ url: '/settings/profile', method: 'patch' });
        route.url = () => '/settings/profile';
        route.method = 'patch';

        expect(formPropsFor(route)).toEqual({
            action: '/settings/profile?_method=PATCH',
            method: 'post',
        });
    });

    it.each([
        ['put', 'PUT'],
        ['delete', 'DELETE'],
    ])('spoofs %s the same way', (method, spoofed) => {
        const route = () => ({ url: '/resource', method });
        route.url = () => '/resource';
        route.method = method;

        expect(formPropsFor(route)).toEqual({
            action: `/resource?_method=${spoofed}`,
            method: 'post',
        });
    });

    it('leaves POST routes untouched', () => {
        const route = () => ({ url: '/login', method: 'post' });
        route.url = () => '/login';
        route.method = 'post';

        expect(formPropsFor(route)).toEqual({
            action: '/login',
            method: 'post',
        });
    });

    it('keeps GET routes as GET', () => {
        const route = () => ({ url: '/search', method: 'get' });
        route.url = () => '/search';
        route.method = 'get';

        expect(formPropsFor(route)).toEqual({
            action: '/search',
            method: 'get',
        });
    });

    it('appends _method with & when the url already has a query string', () => {
        const route = () => ({ url: '/posts/1?draft=1', method: 'patch' });
        route.url = () => '/posts/1?draft=1';
        route.method = 'patch';

        expect(formPropsFor(route).action).toBe(
            '/posts/1?draft=1&_method=PATCH',
        );
    });

    it('never emits a verb an HTML form cannot issue', () => {
        for (const method of ['patch', 'put', 'delete', 'post', 'get']) {
            const route = () => ({ url: '/x', method });
            route.url = () => '/x';
            route.method = method;

            expect(['get', 'post']).toContain(formPropsFor(route).method);
        }
    });

    it('falls back to the route definition when url()/method are absent', () => {
        const route = () => ({});
        (route as unknown as Record<string, unknown>).definition = {
            url: '/fallback',
            methods: ['patch'],
        };

        expect(formPropsFor(route)).toEqual({
            action: '/fallback?_method=PATCH',
            method: 'post',
        });
    });
});

describe('withForm', () => {
    it('attaches a form() helper that spoofs the method', () => {
        const route = () => ({ url: '/settings/profile', method: 'patch' });
        route.url = () => '/settings/profile';
        route.method = 'patch';

        expect(withForm(route).form()).toEqual({
            action: '/settings/profile?_method=PATCH',
            method: 'post',
        });
    });

    it('preserves a natively generated form() from wayfinder', () => {
        const native = { action: '/native', method: 'post' as const };
        const route = () => ({ url: '/x', method: 'patch' });
        route.url = () => '/x';
        route.method = 'patch';
        (route as unknown as Record<string, unknown>).form = () => native;

        expect(withForm(route).form()).toBe(native);
    });

    it('does not expose form() to Object.values walks over route modules', () => {
        const route = () => ({ url: '/x', method: 'patch' });
        route.url = () => '/x';
        route.method = 'patch';

        withForm(route);

        expect(Object.keys(route)).not.toContain('form');
    });
});
