import { router } from '@inertiajs/vue3';
import Lenis from 'lenis';
import { ref } from 'vue';
import type { Ref } from 'vue';
import 'lenis/dist/lenis.css';

/**
 * A singleton wrapper around Lenis smooth scroll.
 *
 * - Initialises one Lenis instance for the entire app
 * - Syncs with GSAP ScrollTrigger (call `syncScrollTrigger()` in components that use it)
 * - Handles Inertia page transitions (`lenis.resize()` on navigate)
 * - Respects `prefers-reduced-motion`
 * - Cleans up on `destroy()`
 */

let lenisInstance: Lenis | null = null;
let cleanupListeners: (() => void) | null = null;

/** Reactive ref so Vue components can react to scroll progress */
export const lenisScroll: Ref<number> = ref(0);
export const lenisProgress: Ref<number> = ref(0);

export interface LenisConfig {
    duration?: number;
    smoothWheel?: boolean;
    wheelMultiplier?: number;
    touchMultiplier?: number;
    anchors?: boolean;
    autoRaf?: boolean;
}

const defaultConfig: LenisConfig = {
    duration: 1.2,
    smoothWheel: true,
    wheelMultiplier: 1,
    touchMultiplier: 1.5,
    anchors: true,
    autoRaf: true,
};

/**
 * Whether the current device is low-tier hardware that should avoid the
 * per-frame cost of a smooth-scroll engine.
 *
 * Mirrors the heuristics in `useMobile`'s `isLowEndDevice` so the global
 * Lenis instance shares the same signal instead of only honoring
 * `prefers-reduced-motion`. Lenis runs its own `requestAnimationFrame`
 * virtual-scroll loop and `scroll` → `ScrollTrigger.update()` churn, which is
 * a leading source of frame drops on coarse-pointer / low-memory / few-core
 * devices — even when the page's own GSAP is already disabled.
 */
interface DeviceMemory extends Navigator {
    deviceMemory?: number;
}
interface NavigatorWithConnection extends Navigator {
    connection?: { effectiveType?: string };
}

export function isLowEndDeviceSignal(): boolean {
    if (typeof window === 'undefined') return false;
    const reduced = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    if (reduced) return true;

    const coarse =
        'ontouchstart' in window ||
        navigator.maxTouchPoints > 0 ||
        window.matchMedia('(pointer: coarse)').matches;
    if (coarse) return true;

    const mem = (navigator as DeviceMemory).deviceMemory;
    if (mem !== undefined && mem !== null && mem <= 4) return true;

    const cores = navigator.hardwareConcurrency;
    if (cores !== undefined && cores !== null && cores <= 4) return true;

    const conn = (navigator as NavigatorWithConnection).connection
        ?.effectiveType;
    if (conn === 'slow-2g' || conn === '2g') return true;

    return false;
}

/**
 * Create (or return existing) global Lenis instance.
 * Call once from `app.ts` after the app mounts.
 */
export function initLenis(config: LenisConfig = {}): Lenis | null {
    if (lenisInstance) {
        lenisInstance.resize();
        return lenisInstance;
    }

    // On low-end hardware, skip the smooth-scroll engine entirely — its
    // rAF loop + ScrollTrigger.update() churn is a persistent per-frame cost
    // that the page-level GSAP gating (data-low-end) cannot turn off.
    if (isLowEndDeviceSignal()) return null;

    const prefersReducedMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const opts = { ...defaultConfig, ...config };

    lenisInstance = new Lenis({
        duration: opts.duration,
        smoothWheel: !prefersReducedMotion && opts.smoothWheel,
        wheelMultiplier: opts.wheelMultiplier,
        touchMultiplier: opts.touchMultiplier,
        orientation: 'vertical',
        gestureOrientation: 'vertical',
        anchors: opts.anchors,
        autoRaf: opts.autoRaf,
        autoResize: true,
        overscroll: true,
        stopInertiaOnNavigate: true,
    });

    // Expose scroll progress reactively
    lenisInstance.on('scroll', (l: Lenis) => {
        lenisScroll.value = l.animatedScroll;
        lenisProgress.value = l.progress;
    });

    // Recalculate dimensions after each Inertia navigation
    const removeFinishListener = router.on('finish', () => {
        lenisInstance?.resize();
        // Small delay to let DOM settle
        setTimeout(() => lenisInstance?.resize(), 100);
    });

    cleanupListeners = () => {
        removeFinishListener();
    };

    return lenisInstance;
}

/**
 * Sync GSAP ScrollTrigger with Lenis.
 * Call this inside `onMounted` / GSAP context in components that use ScrollTrigger.
 *
 * @example
 * import { syncLenisWithGsap } from '@/composables/useLenis';
 *
 * onMounted(() => {
 *   const cleanup = syncLenisWithGsap(ScrollTrigger);
 *   onUnmounted(() => cleanup());
 * });
 */
export function syncLenisWithGsap(ScrollTrigger: any): () => void {
    const lenis = getLenis();
    if (!lenis) return () => {};

    const onScroll = () => ScrollTrigger.update();
    lenis.on('scroll', onScroll);

    return () => {
        lenis.off('scroll', onScroll);
    };
}

/**
 * Get the current Lenis instance (or null if not yet initialised).
 */
export function getLenis(): Lenis | null {
    return lenisInstance;
}

/**
 * Destroy Lenis and clean up all listeners.
 */
export function destroyLenis(): void {
    cleanupListeners?.();
    cleanupListeners = null;
    lenisInstance?.destroy();
    lenisInstance = null;
    lenisScroll.value = 0;
    lenisProgress.value = 0;
}
