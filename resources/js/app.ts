import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import GlobalLoader from '@/components/GlobalLoader.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { useLoader } from '@/composables/useLoader';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const { isVisible, show, hide, hideWhenReady } = useLoader();

/**
 * Handle Global Navigation Transitions for the Boot Loader
 */
router.on('start', (event) => {
    const visit = event.detail.visit;
    const method = String(visit.method ?? 'get').toLowerCase();
    const rawUrl = visit.url as string | URL;
    const url = typeof rawUrl === 'string' ? rawUrl : rawUrl?.toString?.() ?? '';

    let targetPath = window.location.pathname;
    try {
        targetPath = new URL(url, window.location.origin).pathname;
    } catch {
        // Keep current pathname fallback when URL parsing fails.
    }

    const isAuthPage =
        window.location.pathname.includes('/login') ||
        window.location.pathname.includes('/register') ||
        window.location.pathname.includes('/two-factor-challenge');

    const isAuthTarget =
        targetPath.includes('/login') ||
        targetPath.includes('/register') ||
        targetPath.includes('/two-factor-challenge');

    const isMutatingVisit = method !== 'get';
    const isAuthFlow = isMutatingVisit && (isAuthPage || isAuthTarget);
    const isLogout = isMutatingVisit && targetPath.includes('/logout');

    console.log(`[app.ts] Navigation started to: ${targetPath}. isAuthFlow: ${isAuthFlow}, isLogout: ${isLogout}`);

    if (isAuthFlow) {
        show('AUTHENTICATING');
    } else if (isLogout) {
        show('TERMINATING SESSION');
    }
});

router.on('finish', (event) => {
    // Signal the loader to hide
    if (isVisible.value) {
        // Use the errors from the event detail if available, or the current page
        const page = event.detail.page || router.page;
        const errors = page?.props?.errors || {};
        const hasErrors = Object.keys(errors).length > 0;
        
        console.log(`[app.ts] Navigation finished. hasErrors: ${hasErrors}, isVisible: ${isVisible.value}`);

        if (hasErrors) {
            console.log('[app.ts] Validation errors detected — hiding loader immediately');
            hide();
        } else {
            // Normal successful navigation — wait for progress bar to hit 100%
            console.log('[app.ts] Successful navigation — calling hideWhenReady');
            hideWhenReady();
        }
    }
});

router.on('error', () => {
    hide();
});

/**
 * Ensure all route objects have form() method
 */
function ensureFormMethod(route: any): void {
    if (!route || typeof route !== 'function') return;
    if (typeof route.form === 'function') return;

    const method = route.method || (route.definition?.methods?.[0]) || 'post';
    const urlFunc = route.url;
    if (!urlFunc) return;

    route.form = () => ({
        action: typeof urlFunc === 'function' ? urlFunc() : urlFunc,
        method: method.toUpperCase?.() || method,
    });
}

/**
 * Patch all routes with form() method, then initialize
 */
(async () => {
    try {
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

        modules.forEach((mod) => {
            if (mod.default) {
                Object.values(mod.default).forEach(route => ensureFormMethod(route));
            }
            Object.keys(mod)
                .filter(key => key !== 'default')
                .forEach(key => {
                    const route = (mod as any)[key];
                    if (route && typeof route === 'object') {
                        ensureFormMethod(route);
                        Object.values(route).forEach(nested => {
                            if (nested && typeof nested === 'object') {
                                ensureFormMethod(nested);
                            }
                        });
                    }
                });
        });

        createInertiaApp({
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup({ el, App, props, plugin }) {
                const RootApp = {
                    name: 'RootApp',
                    setup() {
                        const { isVisible } = useLoader();
                        return { isVisible };
                    },
                    render() {
                        return h('div', [
                            h(App, props),
                            h(GlobalLoader, { show: (this as any).isVisible }),
                        ]);
                    },
                };

                createApp(RootApp)
                    .use(plugin)
                    .mount(el);
            },
            progress: false,
        });

        initializeTheme();
    } catch (error) {
        console.error('[App] Failed to initialize:', error);
    }
})();
