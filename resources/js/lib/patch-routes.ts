/**
 * Synchronous route patching that happens at module load time
 * This patches routes before components can import them
 */

/**
 * Patches a route method object to add form() helper
 */
function patchRouteMethod(routeObj: any): void {
    if (!routeObj || typeof routeObj !== 'object') return;

    // If form() already exists, don't overwrite
    if (typeof routeObj.form === 'function') return;

    // Add form() method if we have url and method
    if (routeObj.url && routeObj.method) {
        const urlValue = routeObj.url;
        const methodValue = routeObj.method;

        Object.defineProperty(routeObj, 'form', {
            value: () => ({
                action: typeof urlValue === 'function' ? urlValue() : urlValue,
                method: methodValue,
            }),
            enumerable: false,
            writable: false,
            configurable: true,
        });
    }
}

/**
 * Recursively patch all exported values from a module
 */
function patchModuleExports(module: any): void {
    if (!module || typeof module !== 'object') return;

    Object.values(module).forEach((exported: any) => {
        patchRouteMethod(exported);
        if (
            exported &&
            typeof exported === 'object' &&
            !Array.isArray(exported)
        ) {
            Object.values(exported).forEach((nested: any) => {
                patchRouteMethod(nested);
            });
        }
    });
}

/**
 * Immediately patch route modules synchronously
 * This runs when this module is imported
 */
export function initRoutePatching(): void {}

// Initialize immediately
initRoutePatching();

// Also set up a global wrapper for future imports
declare global {
    interface Window {
        __patchRoute?: (mod: any) => any;
    }
}

if (typeof window !== 'undefined') {
    window.__patchRoute = patchModuleExports;
}

export { patchRouteMethod, patchModuleExports };
