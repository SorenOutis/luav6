import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import '../css/activities-mobile.css';
import GlobalLoader from '@/components/GlobalLoader.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { initLenis } from '@/composables/useLenis';
import { useLoader } from '@/composables/useLoader';
import { applyLowEndDocumentFlag } from '@/lib/device';
import { formPropsFor } from '@/lib/route-helpers';

// Stamp data-low-end before the async route-patch + Inertia boot so the
// first paint already disables backdrop-filter / infinite CSS animation.
applyLowEndDocumentFlag();

const metaContent = (name: string): string | undefined =>
    document
        .querySelector<HTMLMetaElement>(`meta[name="${name}"]`)
        ?.content.trim() || undefined;

const pusherKey = metaContent('pusher-key');
const pusherCluster = metaContent('pusher-cluster');

// Read Pusher's public client identifiers from runtime-rendered meta tags.
// This works with container/runtime environment variables; Vite does not need
// production credentials while compiling the frontend image.
if (pusherKey) {
    configureEcho({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster ?? 'mt1',
        forceTLS: true,
    });
} else {
    // Keep local/test environments without Pusher credentials functional.
    configureEcho({ broadcaster: 'null' });
}

// APP_NAME is rendered by Laravel at request time, so changing it does not
// require a frontend rebuild. VITE_APP_NAME remains a safe build-time fallback.
const appName =
    metaContent('app-name') || import.meta.env.VITE_APP_NAME || 'Laravel';
const { isVisible, show, hide, hideWhenReady } = useLoader();

// Navigation logging is a debugging aid, not a production feature. Writing to
// the console on every visit costs main-thread time (and in DevTools-open
// sessions, a lot of it) right at the moment the browser should be rendering
// the next page. Keep it in dev only.
const DEV = import.meta.env.DEV;
const debug = (...args: unknown[]) => {
    if (DEV) console.log(...args);
};

// Delay before the global loader appears, so in-button spinners are visible first.
const GLOBAL_LOADER_DELAY_MS = 250;
let pendingShowTimer: ReturnType<typeof setTimeout> | null = null;

const showDeferred = (message: string) => {
    if (pendingShowTimer) clearTimeout(pendingShowTimer);
    pendingShowTimer = setTimeout(() => {
        show(message);
        pendingShowTimer = null;
    }, GLOBAL_LOADER_DELAY_MS);
};

const cancelPendingShow = () => {
    if (pendingShowTimer) {
        clearTimeout(pendingShowTimer);
        pendingShowTimer = null;
    }
};

/**
 * Clear user-specific localStorage/sessionStorage on logout
 * to prevent the next user on the same machine from seeing previous data.
 */
const clearUserStorage = () => {
    // Clear exam auto-save drafts (sensitive — contains answers)
    const keysToRemove: string[] = [];
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('exam_draft_')) {
            keysToRemove.push(key);
        }
    }
    keysToRemove.forEach((key) => localStorage.removeItem(key));

    // Clear leaderboard preferences
    localStorage.removeItem('leaderboard-selected-section-id');
    localStorage.removeItem('leaderboard-blurred-sections');

    // Clear section selection
    localStorage.removeItem('selectedSectionId');

    // Clear appearance/font settings so they don't carry over to the welcome page
    localStorage.removeItem('appearance');
    localStorage.removeItem('themePreset');
    localStorage.removeItem('fontPreset');
    localStorage.removeItem('cardStylePreset');
    localStorage.removeItem('dyslexia-friendly');

    // Clear corresponding cookies
    const cookieKeys = [
        'appearance',
        'themePreset',
        'fontPreset',
        'cardStylePreset',
    ];
    cookieKeys.forEach((key) => {
        document.cookie = `${key}=;path=/;max-age=0;SameSite=Lax`;
    });

    // Reset HTML data attributes to defaults
    document.documentElement.dataset.themePreset = 'default';
    document.documentElement.dataset.fontPreset = 'system';
    document.documentElement.dataset.cardStyle = 'current';

    // Remove dyslexia-friendly class
    document.documentElement.classList.remove('dyslexia-friendly');

    // Clear session flags
    sessionStorage.removeItem('logged_out');
};

/**
 * Handle Global Navigation Transitions for the Boot Loader
 */
router.on('start', (event) => {
    const visit = event.detail.visit;
    const method = String(visit.method ?? 'get').toLowerCase();
    const rawUrl = visit.url as string | URL;
    const url =
        typeof rawUrl === 'string' ? rawUrl : (rawUrl?.toString?.() ?? '');

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

    debug(
        `[app.ts] Navigation started to: ${targetPath}. isAuthFlow: ${isAuthFlow}, isLogout: ${isLogout}`,
    );

    if (isAuthFlow) {
        showDeferred('Signing in...');
    } else if (isLogout) {
        clearUserStorage();
        showDeferred('Signing out...');
    }
});

router.on('finish', (event) => {
    // If the request finished before the deferred loader appeared, cancel it.
    cancelPendingShow();

    // Signal the loader to hide
    if (isVisible.value) {
        // Use the errors from the event detail if available.
        // (Inertia v2 no longer exposes router.page; the finish event carries it.)
        const detail = event.detail as unknown as {
            page?: { props?: { errors?: Record<string, unknown> } };
        };
        const page = detail.page;
        const errors = page?.props?.errors || {};
        const hasErrors = Object.keys(errors).length > 0;

        debug(
            `[app.ts] Navigation finished. hasErrors: ${hasErrors}, isVisible: ${isVisible.value}`,
        );

        if (hasErrors) {
            debug(
                '[app.ts] Validation errors detected — hiding loader immediately',
            );
            hide();
        } else {
            // Normal successful navigation — wait for progress bar to hit 100%
            debug('[app.ts] Successful navigation — calling hideWhenReady');
            hideWhenReady();
        }
    }
});

router.on('error', () => {
    cancelPendingShow();
    hide();
});

/**
 * Ensure all route objects have a form() method.
 *
 * Delegates to the shared helper so the runtime patch and the `withForm()`
 * wrapper used by pages produce identical attributes. Crucially that means
 * PATCH/PUT/DELETE routes are spoofed as POST + `_method`, which is what
 * makes multipart file uploads (avatar, cover photo) reach PHP at all.
 */
function ensureFormMethod(route: any): void {
    if (!route || typeof route !== 'function') return;
    if (typeof route.form === 'function') return;
    if (!route.url && !route.definition?.url) return;

    Object.defineProperty(route, 'form', {
        value: () => formPropsFor(route),
        configurable: true,
        enumerable: false,
        writable: true,
    });
}

/**
 * Patch all routes with form() method, then initialize
 */
(async () => {
    try {
        const modules = await Promise.allSettled([
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

        modules.forEach((result, index) => {
            if (result.status === 'fulfilled') {
                const mod = result.value;
                if (mod.default) {
                    Object.values(mod.default).forEach((route) =>
                        ensureFormMethod(route),
                    );
                }
                Object.keys(mod)
                    .filter((key) => key !== 'default')
                    .forEach((key) => {
                        const route = (mod as any)[key];
                        if (route && typeof route === 'object') {
                            ensureFormMethod(route);
                            Object.values(route).forEach((nested) => {
                                if (nested && typeof nested === 'object') {
                                    ensureFormMethod(nested);
                                }
                            });
                        }
                    });
            } else {
                console.warn(
                    `[App] Failed to patch module at index ${index}:`,
                    result.reason,
                );
            }
        });
    } catch (error) {
        console.error('[App] Unexpected error during module patching:', error);
    }

    try {
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

                createApp(RootApp).use(plugin).mount(el);
            },
            // The full-screen GlobalLoader is reserved for auth flows, so
            // ordinary page clicks previously had NO feedback at all — the UI
            // just sat there until the response landed, which reads as "the
            // app is frozen" even on a fast request. The top progress bar is
            // the standard fix: 250ms delay means quick, cached or prefetched
            // navigations never flash it, while anything slower immediately
            // tells the user the click registered.
            progress: {
                delay: 250,
                color: '#f59e0b',
                showSpinner: false,
            },
        });

        initializeTheme();

        // Re-stamp after boot in case matchMedia was not ready at module load.
        applyLowEndDocumentFlag();

        // Initialise Lenis smooth scroll globally
        initLenis();
    } catch (error) {
        console.error('[App] Failed to initialize Inertia application:', error);
    }
})();
