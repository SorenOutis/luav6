import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

interface DeviceMemory extends Navigator {
    deviceMemory?: number;
}

interface NetworkInformation extends EventTarget {
    effectiveType?: 'slow-2g' | '2g' | '3g' | '4g';
    addEventListener(
        type: string,
        listener: EventListenerOrEventListenerObject,
        options?: boolean | AddEventListenerOptions,
    ): void;
    removeEventListener(
        type: string,
        listener: EventListenerOrEventListenerObject,
        options?: boolean | EventListenerOptions,
    ): void;
}

interface NavigatorWithConnection extends Navigator {
    connection?: NetworkInformation;
}

export function useMobile() {
    const isMobile = ref(false);
    const isTouchDevice = ref(false);
    const isCoarsePointer = ref(false);
    const prefersReducedMotion = ref(false);
    const deviceMemory = ref<number | null>(null);
    const hardwareConcurrency = ref<number | null>(null);
    const connectionType = ref<'slow-2g' | '2g' | '3g' | '4g' | null>(null);

    const BREAKPOINT_MOBILE = 640; // sm:

    // ─── Debounced resize handler ───
    let resizeTimer: ReturnType<typeof setTimeout> | null = null;
    const RESIZE_DEBOUNCE_MS = 150;

    const update = () => {
        isMobile.value = window.innerWidth < BREAKPOINT_MOBILE;
        isTouchDevice.value =
            'ontouchstart' in window || navigator.maxTouchPoints > 0;
        isCoarsePointer.value =
            isTouchDevice.value ||
            window.matchMedia('(pointer: coarse)').matches;
        prefersReducedMotion.value = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        // Read hardware signals (Chrome-only for deviceMemory)
        const navMem = navigator as DeviceMemory;
        deviceMemory.value = navMem.deviceMemory ?? null;
        hardwareConcurrency.value = navigator.hardwareConcurrency ?? null;

        const navConn = navigator as NavigatorWithConnection;
        connectionType.value = navConn.connection?.effectiveType ?? null;
    };

    /**
     * Combined low-end device signal.
     * Returns true when at least one of these heuristics indicates lower-tier hardware:
     * - Coarse pointer (touch / mobile)
     * - prefers-reduced-motion (user-requested)
     * - ≤ 4 GB RAM (Chrome deviceMemory API)
     * - ≤ 4 CPU cores (hardwareConcurrency)
     * - Slow connection (2g / slow-2g)
     */
    const isLowEndDevice = computed(() => {
        if (isCoarsePointer.value || prefersReducedMotion.value) return true;
        if (deviceMemory.value !== null && deviceMemory.value <= 4) return true;
        if (
            hardwareConcurrency.value !== null &&
            hardwareConcurrency.value <= 4
        )
            return true;
        if (connectionType.value === 'slow-2g' || connectionType.value === '2g')
            return true;
        return false;
    });

    let mqlMobile: MediaQueryList | null = null;
    let mqlCoarsePointer: MediaQueryList | null = null;
    let mqlReducedMotion: MediaQueryList | null = null;
    let connection: NetworkInformation | null = null;

    const onMqlUpdate = () => update();

    const onResizeDebounced = () => {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(update, RESIZE_DEBOUNCE_MS);
    };

    onMounted(() => {
        update();

        mqlMobile = window.matchMedia(
            `(max-width: ${BREAKPOINT_MOBILE - 1}px)`,
        );
        mqlCoarsePointer = window.matchMedia('(pointer: coarse)');
        mqlReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );

        mqlMobile.addEventListener('change', onMqlUpdate);
        mqlCoarsePointer.addEventListener('change', onMqlUpdate);
        mqlReducedMotion.addEventListener('change', onMqlUpdate);

        // Connection change listener
        const navConn = navigator as NavigatorWithConnection;
        connection = navConn.connection ?? null;
        if (connection) {
            connection.addEventListener('change', onMqlUpdate);
        }

        // Debounced resize listener
        window.addEventListener('resize', onResizeDebounced);
    });

    onBeforeUnmount(() => {
        mqlMobile?.removeEventListener('change', onMqlUpdate);
        mqlCoarsePointer?.removeEventListener('change', onMqlUpdate);
        mqlReducedMotion?.removeEventListener('change', onMqlUpdate);

        if (connection) {
            connection.removeEventListener('change', onMqlUpdate);
        }

        window.removeEventListener('resize', onResizeDebounced);
        if (resizeTimer) clearTimeout(resizeTimer);
    });

    return {
        isMobile,
        isTouchDevice,
        isCoarsePointer,
        prefersReducedMotion,
        deviceMemory,
        hardwareConcurrency,
        connectionType,
        isLowEndDevice,
    };
}
