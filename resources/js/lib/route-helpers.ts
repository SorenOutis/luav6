/**
 * The HTTP methods supported by the wayfinder-generated route types.
 *
 * The generated `@/wayfinder` module declares `type Method` locally without
 * exporting it, so this union is replicated here.
 */
export type FormMethod =
    'get' | 'post' | 'put' | 'delete' | 'patch' | 'head' | 'options';

/**
 * The HTTP methods accepted by Inertia's `<Form>` component (`Method`).
 */
export type RouteFormMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

/**
 * Type-only helper for the `.form()` method that `resources/js/app.ts`
 * attaches to wayfinder route functions at runtime (`ensureFormMethod`).
 * The generated modules cannot declare it themselves, so pages wrap their
 * route/action imports with this before calling `.form()`.
 */
export function withForm<T extends (...args: any[]) => unknown>(
    route: T,
): T & { form(): { action: string; method: RouteFormMethod } } {
    return route as T & { form(): { action: string; method: RouteFormMethod } };
}
