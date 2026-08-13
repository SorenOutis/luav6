import type { RouteDefinition } from '@/wayfinder';

/**
 * The HTTP methods supported by the wayfinder-generated route types.
 *
 * The generated `@/wayfinder` module declares `type Method` locally without
 * exporting it, so this union is replicated here for type augmentation.
 */
export type FormMethod =
    'get' | 'post' | 'put' | 'delete' | 'patch' | 'head' | 'options';

/**
 * Augment route definitions with form() method for Inertia.js compatibility
 */
export type FormRouteDefinition<TMethod extends FormMethod> =
    RouteDefinition<TMethod> & {
        form?(): {
            action: string;
            method: TMethod;
        };
    };
