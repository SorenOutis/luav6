import type { RouteDefinition } from '@/wayfinder'

/**
 * Augment route definitions with form() method for Inertia.js compatibility
 */
export interface FormRouteDefinition<TMethod extends string> extends RouteDefinition<TMethod> {
    form?(): {
        action: string
        method: TMethod
    }
}
