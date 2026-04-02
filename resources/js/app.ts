import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Ensure all route objects have form() method
 */
function ensureFormMethod(route: any): void {
    if (!route || typeof route !== 'function') return;
    
    // Skip if already has form method
    if (typeof route.form === 'function') return;
    
    // Get method from the route definition
    const method = route.method || (route.definition?.methods?.[0]) || 'post';
    
    // Get URL function
    const urlFunc = route.url;
    if (!urlFunc) return;
    
    // Add form() method
    route.form = () => ({
        action: typeof urlFunc === 'function' ? urlFunc() : urlFunc,
        method: method.toUpperCase?.() || method,
    });
    
    console.log('[Patch] Added form() to route, method:', method);
}

/**
 * Patch all routes with form() method before app renders
 */
(async () => {
    try {
        console.log('[Patch] Starting route patching...');
        
        // Load all routes in parallel
        const modules = await Promise.all([
            import('@/routes/login'),
            import('@/routes/register'),
            import('@/routes/password'),
            import('@/routes/verification'),
            import('@/routes/two-factor'),
            import('@/routes/two-factor/login'),
            import('@/routes/password/confirm'),
            import('@/actions/App/Http/Controllers/Settings/ProfileController'),
            import('@/actions/App/Http/Controllers/Settings/PasswordController'),
        ]);

        console.log('[Patch] Modules loaded, patching...');

        // Process each module
        modules.forEach((mod, index) => {
            // Patch default export
            if (mod.default) {
                Object.values(mod.default).forEach(route => {
                    ensureFormMethod(route);
                });
            }
            
            // Patch named exports
            Object.keys(mod)
                .filter(key => key !== 'default')
                .forEach(key => {
                    const route = (mod as any)[key];
                    if (route && typeof route === 'object') {
                        // For simple route objects
                        ensureFormMethod(route);
                        
                        // For nested exports (like route containers with store, update, etc)
                        if (typeof route === 'object') {
                            Object.values(route).forEach(nested => {
                                if (nested && typeof nested === 'object') {
                                    ensureFormMethod(nested);
                                }
                            });
                        }
                    }
                });
        });

        console.log('[Patch] All routes patched, initializing app...');

        // Now initialize the app
        createInertiaApp({
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) => {
                console.log('[Resolve] Resolving page:', name);
                return resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                );
            },
            setup({ el, App, props, plugin }) {
                createApp({ render: () => h(App, props) })
                    .use(plugin)
                    .mount(el);
            },
            progress: {
                color: '#4B5563',
            },
        });

        console.log('[Patch] App initialized successfully');

        // This will set light / dark mode on page load...
        initializeTheme();
    } catch (error) {
        console.error('[Patch] Failed to initialize app:', error);
    }
})();
