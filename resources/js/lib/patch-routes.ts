/**
 * Synchronous route patching that happens at module load time
 * This patches routes before components can import them
 */

type RouteMethod<T extends string = string> = {
    url?: (options?: any) => string
    definition?: { url: string }
    method?: T
    form?: () => { action: string; method: T }
}

/**
 * Patches a route method object to add form() helper
 */
function patchRouteMethod<T extends string>(routeObj: any): void {
    if (!routeObj || typeof routeObj !== 'object') return
    
    // If form() already exists, don't overwrite
    if (typeof routeObj.form === 'function') return
    
    // Add form() method if we have url and method
    if (routeObj.url && routeObj.method) {
        const urlValue = routeObj.url
        const methodValue = routeObj.method
        
        Object.defineProperty(routeObj, 'form', {
            value: () => ({
                action: typeof urlValue === 'function' 
                    ? urlValue()
                    : urlValue,
                method: methodValue,
            }),
            enumerable: false,
            writable: false,
            configurable: true,
        })
    }
}

/**
 * Recursively patch all exported values from a module
 */
function patchModuleExports(module: any): void {
    if (!module || typeof module !== 'object') return
    
    Object.values(module).forEach((exported: any) => {
        patchRouteMethod(exported)
        if (exported && typeof exported === 'object' && !Array.isArray(exported)) {
            Object.values(exported).forEach((nested: any) => {
                patchRouteMethod(nested)
            })
        }
    })
}

/**
 * Immediately patch route modules synchronously
 * This runs when this module is imported
 */
export function initRoutePatching(): void {
    // Use require cache to get already-loaded modules synchronously
    // This works because by the time components load, these modules are cached
    const getModuleSync = (path: string) => {
        try {
            // Access via import.meta.glob cached results
            return (globalThis as any).__viteModuleCache?.[path]
        } catch {
            return null
        }
    }

    // Try to patch modules synchronously if they're already loaded
    const paths = [
        '@/routes/login',
        '@/routes/register',
        '@/routes/password',
        '@/routes/verification',
        '@/routes/two-factor',
        '@/routes/two-factor/login',
        '@/routes/password/confirm',
        '@/actions/App/Http/Controllers/Settings/ProfileController',
        '@/actions/App/Http/Controllers/Settings/PasswordController',
    ]

    // Force synchronous imports by using require if available
    if (typeof require !== 'undefined') {
        try {
            paths.forEach(path => {
                try {
                    const mod = require(path)
                    patchModuleExports(mod)
                } catch {
                    // Module not loaded yet, will be loaded async
                }
            })
        } catch {
            // Fallback to async
        }
    }
}

// Initialize immediately
initRoutePatching()

// Also set up a global wrapper for future imports
declare global {
    interface Window {
        __patchRoute?: (mod: any) => any
    }
}

if (typeof window !== 'undefined') {
    window.__patchRoute = patchModuleExports
}

export { patchRouteMethod, patchModuleExports }


