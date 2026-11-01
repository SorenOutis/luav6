import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import {
    BREAKPOINT_DESKTOP,
    BREAKPOINT_MOBILE,
    addMqlListener,
    applyLowEndDocumentFlag,
    emptyDeviceSnapshot,
    isLowEndDeviceFrom,
    readDeviceSnapshot,
    removeMqlListener,
    safeMatchMediaList,
} from '@/lib/device';
import type { ConnectionEffectiveType } from '@/lib/device';

interface NetworkInformation extends EventTarget {
    effectiveType?: ConnectionEffectiveType;
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
    // Seed from the current device *during setup*. Waiting until onMounted
    // meant the welcome page (and every other consumer) rendered the desktop
    // animation path for a frame on phones, then tore it down — that's the
    // hitch users feel as "laggy".
    const initial =
        typeof window === 'undefined'
            ? emptyDeviceSnapshot()
            : readDeviceSnapshot();

    const isMobile = ref(initial.isMobile);
    const isDesktop = ref(initial.isDesktop);
    const isTouchDevice = ref(initial.isTouchDevice);
    const isCoarsePointer = ref(initial.isCoarsePointer);
    const prefersReducedMotion = ref(initial.prefersReducedMotion);
    const deviceMemory = ref<number | null>(initial.deviceMemory);
    const hardwareConcurrency = ref<number | null>(initial.hardwareConcurrency);
    const connectionType = ref<ConnectionEffectiveType | null>(
        initial.connectionType,
    );

    let resizeTimer: ReturnType<typeof setTimeout> | null = null;
    const RESIZE_DEBOUNCE_MS = 150;

    const applySnapshot = () => {
        const snapshot = readDeviceSnapshot();
        isMobile.value = snapshot.isMobile;
        isDesktop.value = snapshot.isDesktop;
        isTouchDevice.value = snapshot.isTouchDevice;
        isCoarsePointer.value = snapshot.isCoarsePointer;
        prefersReducedMotion.value = snapshot.prefersReducedMotion;
        deviceMemory.value = snapshot.deviceMemory;
        hardwareConcurrency.value = snapshot.hardwareConcurrency;
        connectionType.value = snapshot.connectionType;
        applyLowEndDocumentFlag();
    };

    const isLowEndDevice = computed(() =>
        isLowEndDeviceFrom({
            isMobile: isMobile.value,
            isDesktop: isDesktop.value,
            isTouchDevice: isTouchDevice.value,
            isCoarsePointer: isCoarsePointer.value,
            prefersReducedMotion: prefersReducedMotion.value,
            deviceMemory: deviceMemory.value,
            hardwareConcurrency: hardwareConcurrency.value,
            connectionType: connectionType.value,
        }),
    );

    let mqlMobile: MediaQueryList | null = null;
    let mqlDesktop: MediaQueryList | null = null;
    let mqlCoarsePointer: MediaQueryList | null = null;
    let mqlReducedMotion: MediaQueryList | null = null;
    let connection: NetworkInformation | null = null;

    const onMqlUpdate = () => applySnapshot();

    const onResizeDebounced = () => {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applySnapshot, RESIZE_DEBOUNCE_MS);
    };

    onMounted(() => {
        applySnapshot();

        mqlMobile = safeMatchMediaList(
            `(max-width: ${BREAKPOINT_MOBILE - 1}px)`,
        );
        mqlDesktop = safeMatchMediaList(`(min-width: ${BREAKPOINT_DESKTOP}px)`);
        mqlCoarsePointer = safeMatchMediaList('(pointer: coarse)');
        mqlReducedMotion = safeMatchMediaList(
            '(prefers-reduced-motion: reduce)',
        );

        addMqlListener(mqlMobile, onMqlUpdate);
        addMqlListener(mqlDesktop, onMqlUpdate);
        addMqlListener(mqlCoarsePointer, onMqlUpdate);
        addMqlListener(mqlReducedMotion, onMqlUpdate);

        const navConn = navigator as NavigatorWithConnection;
        connection = navConn.connection ?? null;
        if (connection && typeof connection.addEventListener === 'function') {
            connection.addEventListener('change', onMqlUpdate);
        }

        window.addEventListener('resize', onResizeDebounced);
    });

    onBeforeUnmount(() => {
        removeMqlListener(mqlMobile, onMqlUpdate);
        removeMqlListener(mqlDesktop, onMqlUpdate);
        removeMqlListener(mqlCoarsePointer, onMqlUpdate);
        removeMqlListener(mqlReducedMotion, onMqlUpdate);

        if (
            connection &&
            typeof connection.removeEventListener === 'function'
        ) {
            connection.removeEventListener('change', onMqlUpdate);
        }

        window.removeEventListener('resize', onResizeDebounced);
        if (resizeTimer) clearTimeout(resizeTimer);
    });

    return {
        isMobile,
        isDesktop,
        isTouchDevice,
        isCoarsePointer,
        prefersReducedMotion,
        deviceMemory,
        hardwareConcurrency,
        connectionType,
        isLowEndDevice,
    };
}
