/**
 * Stub for the wayfinder-generated ProfileController action module.
 *
 * The real module is generated into resources/js/routes_temp by an artisan
 * command, so it does not exist in a plain JS test run. The shape mirrors the
 * generated output: a callable carrying `.url()` and `.method`, deliberately
 * WITHOUT a `.form()` — that is the case the app patches at runtime, and the
 * case the profile upload regression depends on.
 */
type RouteFn = {
    (): { url: string; method: string };
    url: () => string;
    method: string;
};

const make = (url: string, method: string): RouteFn => {
    const fn = (() => ({ url, method })) as RouteFn;
    fn.url = () => url;
    fn.method = method;

    return fn;
};

export const edit = make('/settings/profile', 'get');
export const update = make('/settings/profile', 'patch');
export const destroy = make('/settings/profile', 'delete');

export default { edit, update, destroy };
