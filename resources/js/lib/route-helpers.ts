/**
 * The HTTP methods supported by the wayfinder-generated route types.
 *
 * The generated `@/wayfinder` module declares `type Method` locally without
 * exporting it, so this union is replicated here.
 */
export type FormMethod =
    | 'get'
    | 'post'
    | 'put'
    | 'delete'
    | 'patch'
    | 'head'
    | 'options';

/**
 * The HTTP methods accepted by Inertia's `<Form>` component (`Method`).
 */
export type RouteFormMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

/**
 * The attributes an HTML `<form>` (and Inertia's `<Form>`) can actually use.
 *
 * Only `get` and `post` are real HTML form methods, so this is deliberately
 * narrower than `RouteFormMethod`: everything else is method-spoofed.
 */
export type FormProps = {
    action: string;
    method: 'get' | 'post';
};

/**
 * Verbs an HTML form cannot issue natively. Laravel recovers them from the
 * `_method` parameter (`Request::enableHttpMethodParameterOverride()`), which
 * Symfony reads from the POST body first and the query string second.
 */
const SPOOFED_METHODS = new Set(['put', 'patch', 'delete']);

type RouteLike = {
    url?: string | ((...args: unknown[]) => string);
    method?: string;
    definition?: { url?: string; methods?: string[] };
    form?: (...args: unknown[]) => FormProps;
};

const resolveUrl = (route: RouteLike): string => {
    if (typeof route.url === 'function') return route.url();
    if (typeof route.url === 'string') return route.url;

    return route.definition?.url ?? '';
};

const resolveMethod = (route: RouteLike): string =>
    (route.method ?? route.definition?.methods?.[0] ?? 'post').toLowerCase();

/**
 * Build the `<form>` attributes for a wayfinder route.
 *
 * HTML forms — and therefore Inertia's `<Form>` component, which mirrors a
 * real form element — can only issue GET and POST. A PATCH/PUT/DELETE route
 * must be submitted as POST carrying `_method`, exactly like Blade's
 * `@method('PATCH')` directive and like wayfinder's own `--with-form` output:
 *
 *   ProfileController.update.form()
 *   // { action: '/settings/profile?_method=PATCH', method: 'post' }
 *
 * This is not cosmetic. Sending the verb literally is what silently broke
 * avatar / cover uploads: when the form contains a file, Inertia serialises
 * the payload as `multipart/form-data`, and PHP only parses multipart bodies
 * (populating `$_POST` / `$_FILES`) for POST requests. A literal PATCH arrives
 * with an unparsed body, so `$request->hasFile('avatar')` is false and the
 * upload is dropped without any error — the picture simply never saves.
 * Text-only submissions kept working because Inertia sends those as JSON,
 * which Laravel reads fine on PATCH, which is why the bug looked like it was
 * specific to the profile picture.
 */
export function formPropsFor(route: unknown): FormProps {
    const target = route as RouteLike;
    const url = resolveUrl(target);
    const method = resolveMethod(target);

    if (!SPOOFED_METHODS.has(method)) {
        return { action: url, method: method === 'get' ? 'get' : 'post' };
    }

    const separator = url.includes('?') ? '&' : '?';

    return {
        action: `${url}${separator}_method=${method.toUpperCase()}`,
        method: 'post',
    };
}

/**
 * Adds the `.form()` helper that pages bind onto Inertia's `<Form>`.
 *
 * Wayfinder only emits `.form()` when generated with `--with-form`, and this
 * project's generation step does not pass that flag, so the method has to be
 * supplied here. A natively generated `.form()` always wins — it already
 * performs the same method spoofing and understands route parameters.
 */
export function withForm<T extends (...args: any[]) => unknown>(
    route: T,
): T & { form(): FormProps } {
    const target = route as unknown as RouteLike;

    if (typeof target.form !== 'function') {
        // Defined non-enumerably so the patched helper never shows up in the
        // `Object.values()` walks that other tooling performs over route
        // modules.
        Object.defineProperty(target, 'form', {
            value: () => formPropsFor(target),
            configurable: true,
            enumerable: false,
            writable: true,
        });
    }

    return route as T & { form(): FormProps };
}
