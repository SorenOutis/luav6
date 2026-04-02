import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import GlobalLoader from '@/components/GlobalLoader.vue';
import { useLoader } from '@/composables/useLoader';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const { isVisible, show, hide, hideWhenReady } = useLoader();

/**
 * Handle Global Navigation Transitions for the Boot Loader
 */
router.on('start', (event) => {
    const destination = event.detail.visit.url.pathname;
    const isFromAuth = window.location.pathname.includes('/login') || window.location.pathname.includes('/register');
    const isHeadingToDashboard = destination.includes('/dashboard') || destination === '/';

    if (isHeadingToDashboard || isFromAuth) {
        show();
    }
});

router.on('finish', () => {
    // Signal the loader to hide — it will wait until progress bar hits 100%
    hideWhenReady();
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
                createApp({
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
                })
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
