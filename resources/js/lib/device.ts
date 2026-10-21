/**
 * Shared device / capability snapshot.
 *
 * `useMobile()` used to leave every signal `false` until `onMounted`. Child
 * components (welcome hero, particles, feature-card GSAP, dashboard Motion)
 * therefore booted the expensive path on phones and only flipped to the
 * lite path a frame later — the main source of "the welcome page is laggy
 * on mobile".
 *
 * Read the snapshot synchronously whenever `window` exists so the first
 * render already matches the device. The same helpers stamp `data-low-end`
 * on <html> for CSS (backdrop-filter, infinite animations) before paint.
 */

export const BREAKPOINT_MOBILE = 640;
export const BREAKPOINT_DESKTOP = 1024;

export type ConnectionEffectiveType = 'slow-2g' | '2g' | '3g' | '4g';

export interface DeviceSnapshot {
    isMobile: boolean;
    isDesktop: boolean;
    isTouchDevice: boolean;
    isCoarsePointer: boolean;
    prefersReducedMotion: boolean;
    deviceMemory: number | null;
    hardwareConcurrency: number | null;
    connectionType: ConnectionEffectiveType | null;
}

interface DeviceMemoryNavigator extends Navigator {
    deviceMemory?: number;
}

interface NetworkInformation extends EventTarget {
    effectiveType?: ConnectionEffectiveType;
}

interface NavigatorWithConnection extends Navigator {
    connection?: NetworkInformation;
}

export function emptyDeviceSnapshot(): DeviceSnapshot {
    return {
        isMobile: false,
        isDesktop: false,
        isTouchDevice: false,
        isCoarsePointer: false,
        prefersReducedMotion: false,
        deviceMemory: null,
        hardwareConcurrency: null,
        connectionType: null,
    };
}

export function readDeviceSnapshot(): DeviceSnapshot {
    if (typeof window === 'undefined') {
        return emptyDeviceSnapshot();
    }

    const navMem = navigator as DeviceMemoryNavigator;
    const navConn = navigator as NavigatorWithConnection;
    const isTouchDevice =
        'ontouchstart' in window || navigator.maxTouchPoints > 0;
    const isMobileViewport =
        window.innerWidth < BREAKPOINT_MOBILE ||
        (isTouchDevice && window.innerWidth < BREAKPOINT_DESKTOP);

    return {
        isMobile: isMobileViewport,
        isDesktop: window.innerWidth >= BREAKPOINT_DESKTOP,
        isTouchDevice,
        /**
         * The *primary* pointer is coarse — a phone or a tablet. A touch
         * laptop still has a mouse, so `pointer` stays fine there even
         * though `ontouchstart` exists; treating it as coarse used to flag
         * every Windows laptop as low-end and freeze the whole site.
         */
        isCoarsePointer: window.matchMedia('(pointer: coarse)').matches,
        prefersReducedMotion: window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches,
        deviceMemory: navMem.deviceMemory ?? null,
        hardwareConcurrency: navigator.hardwareConcurrency ?? null,
        connectionType: navConn.connection?.effectiveType ?? null,
    };
}

/**
 * Combined low-end signal. True when at least one heuristic says the
 * device should skip continuous animation / smooth-scroll / canvas work:
 * - prefers-reduced-motion
 * - Coarse primary pointer (phone / tablet)
 * - Slow connection (2g / slow-2g)
 * - ≤ 4 GB RAM (Chrome deviceMemory) or ≤ 4 CPU cores, on a screen small
 *   enough to be a phone or tablet
 *
 * A full-size desktop window is never low-end on hardware alone: a 4-core
 * laptop renders transitions and scroll reveals without breaking a sweat,
 * and freezing the whole site for it costs more than it saves.
 */
export function isLowEndDeviceFrom(snapshot: DeviceSnapshot): boolean {
    if (snapshot.prefersReducedMotion) {
        return true;
    }
    if (snapshot.isCoarsePointer) {
        return true;
    }
    if (
        snapshot.connectionType === 'slow-2g' ||
        snapshot.connectionType === '2g'
    ) {
        return true;
    }
    if (!snapshot.isDesktop) {
        if (snapshot.deviceMemory !== null && snapshot.deviceMemory <= 4) {
            return true;
        }
        if (
            snapshot.hardwareConcurrency !== null &&
            snapshot.hardwareConcurrency <= 4
        ) {
            return true;
        }
    }
    return false;
}

export function isLowEndDeviceSignal(): boolean {
    return isLowEndDeviceFrom(readDeviceSnapshot());
}

/**
 * Stamp or clear `data-low-end` on <html>. Safe to call from an inline
 * boot script, from `app.ts`, and whenever the snapshot is refreshed.
 */
export function applyLowEndDocumentFlag(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const lowEnd = isLowEndDeviceSignal();
    if (lowEnd) {
        document.documentElement.setAttribute('data-low-end', '');
    } else {
        document.documentElement.removeAttribute('data-low-end');
    }
    return lowEnd;
}
