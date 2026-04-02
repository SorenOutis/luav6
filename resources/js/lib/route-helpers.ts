import { type RouteDefinition } from '../wayfinder'

/**
 * Enhances a route method with a .form() helper for Inertia.js Form component
 * Returns form configuration { action, method } that can be bound to <Form v-bind="..." />
 */
export function withFormHelper<TMethod extends string>(
    routeMethod: RouteDefinition<TMethod> & {
        url?: (options?: any) => string
        definition?: { url: string }
    },
) {
    return Object.assign(routeMethod, {
        form: () => ({
            action: typeof routeMethod.url === 'function' 
                ? routeMethod.url()
                : routeMethod.definition?.url || '',
            method: routeMethod.method,
        }),
    })
}
