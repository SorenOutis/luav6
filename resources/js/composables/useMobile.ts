import { ref, onMounted, onBeforeUnmount } from 'vue';

export function useMobile() {
    const isMobile = ref(false);
    const isTouchDevice = ref(false);
    const isCoarsePointer = ref(false);
    const prefersReducedMotion = ref(false);

    const BREAKPOINT_MOBILE = 640; // sm:

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
    };

    let mqlMobile: MediaQueryList | null = null;
    let mqlCoarsePointer: MediaQueryList | null = null;
    let mqlReducedMotion: MediaQueryList | null = null;
    const onUpdate = () => update();

    onMounted(() => {
        update();

        mqlMobile = window.matchMedia(
            `(max-width: ${BREAKPOINT_MOBILE - 1}px)`,
        );
        mqlCoarsePointer = window.matchMedia('(pointer: coarse)');
        mqlReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );

        mqlMobile.addEventListener('change', onUpdate);
        mqlCoarsePointer.addEventListener('change', onUpdate);
        mqlReducedMotion.addEventListener('change', onUpdate);
        window.addEventListener('resize', onUpdate);
    });

    onBeforeUnmount(() => {
        mqlMobile?.removeEventListener('change', onUpdate);
        mqlCoarsePointer?.removeEventListener('change', onUpdate);
        mqlReducedMotion?.removeEventListener('change', onUpdate);
        window.removeEventListener('resize', onUpdate);
    });

    return {
        isMobile,
        isTouchDevice,
        isCoarsePointer,
        prefersReducedMotion,
    };
}
