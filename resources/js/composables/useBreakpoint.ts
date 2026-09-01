import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Tailwind's default `md` breakpoint (768px, min-width). Kept as a plain
 * constant (not derived from useMobile()'s BREAKPOINT_MOBILE/DESKTOP, which
 * are 640/1024 and serve a different purpose — device-capability heuristics,
 * not the CSS breakpoint used by `md:` utility classes) so that swapping a
 * `hidden md:block` / `md:hidden` CSS toggle for a real `v-if` doesn't shift
 * the layout switch point.
 */
const MD_BREAKPOINT_PX = 768;

/** Matches the `touch-mobile` cutoff stamped on <html> in app.blade.php. */
const TOUCH_MOBILE_MAX_PX = 1024;

// Read directly off window.innerWidth (like readDeviceSnapshot() in
// lib/device.ts) rather than `matchMedia(...).matches`, so this works
// correctly in test environments (jsdom) whose matchMedia stub doesn't
// evaluate the query against the viewport.
function computeIsMdUp(): boolean {
    if (typeof window === 'undefined') return false;
    return window.innerWidth >= MD_BREAKPOINT_PX;
}

function isCoarsePointer(): boolean {
    if (typeof window === 'undefined') return false;
    const isTouchDevice =
        'ontouchstart' in window || navigator.maxTouchPoints > 0;
    return (
        isTouchDevice ||
        (typeof window.matchMedia === 'function' &&
            window.matchMedia('(pointer: coarse)').matches)
    );
}

/**
 * Mirrors Tailwind's `md:` breakpoint as a reactive boolean, for places that
 * used to rely on `hidden md:block` / `block md:hidden` purely for layout
 * switching (both branches always mounted, one hidden with CSS) and are
 * being converted to a real `v-if` so only one branch ever mounts.
 *
 * Seeded synchronously (before mount) so there's no first-paint flash, same
 * approach as useMobile()/readDeviceSnapshot().
 */
export function useMdBreakpoint() {
    const isMdUp = ref(computeIsMdUp());

    const recompute = () => {
        isMdUp.value = computeIsMdUp();
    };

    let mql: MediaQueryList | null = null;
    let resizeTimer: ReturnType<typeof setTimeout> | null = null;
    const RESIZE_DEBOUNCE_MS = 150;

    const onResizeDebounced = () => {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(recompute, RESIZE_DEBOUNCE_MS);
    };

    onMounted(() => {
        recompute();
        if (typeof window.matchMedia === 'function') {
            mql = window.matchMedia(`(min-width: ${MD_BREAKPOINT_PX}px)`);
            mql.addEventListener('change', recompute);
        }
        window.addEventListener('resize', onResizeDebounced);
    });

    onBeforeUnmount(() => {
        mql?.removeEventListener('change', recompute);
        window.removeEventListener('resize', onResizeDebounced);
        if (resizeTimer) clearTimeout(resizeTimer);
    });

    return { isMdUp };
}

/**
 * Same `md:` breakpoint as useMdBreakpoint(), but additionally honors the
 * `html.touch-mobile` override set in resources/views/app.blade.php: coarse
 * pointer devices under 1024px stay on the mobile layout even when their
 * reported CSS viewport is >= 768px (some phone/tablet browsers expose a
 * wider "desktop site mode" viewport while still being a touch phone).
 *
 * Use this — not the plain useMdBreakpoint() — for any dual-mount split
 * that previously relied on CSS classes covered by that override (see the
 * `html.touch-mobile .dashboard-desktop-composition` /
 * `.mobile-dashboard-composition` rules in app.css). Other dual-mount
 * splits (e.g. the exam question mobile-carousel/desktop-grid, which was
 * never part of that override list) should use plain useMdBreakpoint()
 * instead so behavior doesn't change for them.
 */
export function useDashboardLayoutBreakpoint() {
    const recompute = () => {
        const touchMobile =
            isCoarsePointer() && window.innerWidth < TOUCH_MOBILE_MAX_PX;
        return computeIsMdUp() && !touchMobile;
    };

    const isMdUp = ref(recompute());

    const update = () => {
        isMdUp.value = recompute();
    };

    let mqlMd: MediaQueryList | null = null;
    let mqlCoarse: MediaQueryList | null = null;
    let resizeTimer: ReturnType<typeof setTimeout> | null = null;
    const RESIZE_DEBOUNCE_MS = 150;

    const onResizeDebounced = () => {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(update, RESIZE_DEBOUNCE_MS);
    };

    onMounted(() => {
        update();
        if (typeof window.matchMedia === 'function') {
            mqlMd = window.matchMedia(`(min-width: ${MD_BREAKPOINT_PX}px)`);
            mqlCoarse = window.matchMedia('(pointer: coarse)');
            mqlMd.addEventListener('change', update);
            mqlCoarse.addEventListener('change', update);
        }
        window.addEventListener('resize', onResizeDebounced);
    });

    onBeforeUnmount(() => {
        mqlMd?.removeEventListener('change', update);
        mqlCoarse?.removeEventListener('change', update);
        window.removeEventListener('resize', onResizeDebounced);
        if (resizeTimer) clearTimeout(resizeTimer);
    });

    return { isMdUp };
}
