<script setup lang="ts">
import gsap from 'gsap';
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLoader } from '@/composables/useLoader';

const props = withDefaults(
    defineProps<{
        show: boolean;
        minDisplayMs?: number;
    }>(),
    {
        minDisplayMs: 650,
    },
);

const { pendingHide, hide, message } = useLoader();

const loaderContainer = ref<HTMLElement | null>(null);
const curtainRef = ref<HTMLElement | null>(null);
const contentRef = ref<HTMLElement | null>(null);
const progress = ref(0);

const prefersReducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

let progressDone = false;
let shownAt = 0;
const progressProxy = { val: 0 };

const setInertOnSiblings = (active: boolean) => {
    const el = loaderContainer.value;
    if (!el?.parentElement) return;
    for (const sibling of Array.from(el.parentElement.children)) {
        if (sibling === el) continue;
        if (active) {
            sibling.setAttribute('inert', '');
            sibling.setAttribute('aria-hidden', 'true');
        } else {
            sibling.removeAttribute('inert');
            sibling.removeAttribute('aria-hidden');
        }
    }
};

const tryExit = () => {
    if (!(progressDone && pendingHide.value)) return;
    const elapsed = performance.now() - shownAt;
    const wait = Math.max(0, props.minDisplayMs - elapsed);
    setTimeout(startExit, wait > 0 ? wait : 0);
};

onMounted(() => {
    gsap.set(loaderContainer.value, { autoAlpha: 0, display: 'none' });
    if (curtainRef.value) {
        gsap.set(curtainRef.value, { xPercent: 100 });
    }
    if (props.show) startEntrance();
});

onBeforeUnmount(() => {
    gsap.killTweensOf(progressProxy);
    if (loaderContainer.value) gsap.killTweensOf(loaderContainer.value);
    if (curtainRef.value) gsap.killTweensOf(curtainRef.value);
    if (contentRef.value) gsap.killTweensOf(contentRef.value);
    setInertOnSiblings(false);
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            startEntrance();
        } else if (
            loaderContainer.value &&
            getComputedStyle(loaderContainer.value).display !== 'none'
        ) {
            startExit(true);
        }
    },
);

const startEntrance = () => {
    gsap.killTweensOf(progressProxy);
    if (loaderContainer.value) gsap.killTweensOf(loaderContainer.value);
    if (curtainRef.value) gsap.killTweensOf(curtainRef.value);
    if (contentRef.value) gsap.killTweensOf(contentRef.value);

    progressDone = false;
    progress.value = 0;
    progressProxy.val = 0;
    shownAt = performance.now();

    gsap.set(loaderContainer.value, { display: 'flex', autoAlpha: 1 });
    setInertOnSiblings(true);

    if (prefersReducedMotion) {
        if (curtainRef.value) gsap.set(curtainRef.value, { xPercent: 0 });
        if (contentRef.value) gsap.set(contentRef.value, { opacity: 1, y: 0 });
    } else {
        // Horizontal curtain swipe entrance
        if (curtainRef.value) {
            gsap.fromTo(
                curtainRef.value,
                { xPercent: 100 },
                {
                    xPercent: 0,
                    duration: 0.45,
                    ease: 'power3.out',
                },
            );
        }

        // Minimalist content softly settles into place
        if (contentRef.value) {
            gsap.fromTo(
                contentRef.value,
                { opacity: 0, y: 8 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.35,
                    delay: 0.1,
                    ease: 'power2.out',
                },
            );
        }
    }

    // Realistic progress curve
    const duration = prefersReducedMotion ? 0.4 : 1.2;
    gsap.to(progressProxy, {
        val: pendingHide.value ? 100 : 95,
        duration,
        ease: 'power2.out',
        onUpdate: () => {
            progress.value = Math.floor(progressProxy.val);
        },
        onComplete: () => {
            if (pendingHide.value) {
                progress.value = 100;
                progressProxy.val = 100;
                progressDone = true;
                tryExit();
            } else {
                const stopWatch = watch(
                    pendingHide,
                    (v) => {
                        if (!v) return;
                        stopWatch();
                        gsap.to(progressProxy, {
                            val: 100,
                            duration: 0.25,
                            ease: 'power2.out',
                            onUpdate: () => {
                                progress.value = Math.floor(progressProxy.val);
                            },
                            onComplete: () => {
                                progressDone = true;
                                tryExit();
                            },
                        });
                    },
                    { immediate: true },
                );
            }
        },
    });
};

const startExit = (fast = false) => {
    if (prefersReducedMotion) {
        gsap.to(loaderContainer.value, {
            autoAlpha: 0,
            duration: 0.2,
            ease: 'power2.inOut',
            onComplete: () => {
                gsap.set(loaderContainer.value, { display: 'none' });
                setInertOnSiblings(false);
                hide();
            },
        });
        return;
    }

    // Minimal text & spinner fades
    if (contentRef.value) {
        gsap.to(contentRef.value, {
            opacity: 0,
            y: -6,
            duration: fast ? 0.18 : 0.25,
            ease: 'power2.in',
        });
    }

    // Curtain wipes out horizontally to left, revealing page
    if (curtainRef.value) {
        gsap.to(curtainRef.value, {
            xPercent: -100,
            duration: fast ? 0.35 : 0.45,
            ease: 'power3.inOut',
            onComplete: () => {
                gsap.set(loaderContainer.value, {
                    display: 'none',
                    autoAlpha: 0,
                });
                if (curtainRef.value) {
                    gsap.set(curtainRef.value, { xPercent: 100 });
                }
                if (contentRef.value) {
                    gsap.set(contentRef.value, { opacity: 1, y: 0 });
                }
                setInertOnSiblings(false);
                hide();
            },
        });
    } else {
        gsap.set(loaderContainer.value, { display: 'none', autoAlpha: 0 });
        setInertOnSiblings(false);
        hide();
    }
};

watch(pendingHide, (isPending) => {
    if (isPending) tryExit();
});
</script>

<template>
    <div
        ref="loaderContainer"
        role="status"
        aria-live="polite"
        aria-busy="true"
        :aria-label="`${message}, ${progress}%`"
        class="global-loader fixed inset-0 z-[9999] overflow-hidden font-sans select-none"
        style="display: none; visibility: hidden; opacity: 0"
    >
        <!-- Swipe Curtain: Full-screen canvas adapted to current theme -->
        <div
            ref="curtainRef"
            data-test="global-loader-backdrop"
            class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden bg-background text-foreground transition-colors duration-300"
        >
            <!-- Card-free minimalist center layout -->
            <div
                ref="contentRef"
                data-test="global-loader-editorial"
                class="flex flex-col items-center text-center"
            >
                <!-- Sleek Minimal Spinner -->
                <div
                    class="mb-4 flex items-center justify-center text-brand"
                    aria-hidden="true"
                >
                    <svg
                        class="h-7 w-7 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-20"
                            cx="12"
                            cy="12"
                            r="9"
                            stroke="currentColor"
                            stroke-width="2.5"
                        />
                        <path
                            class="opacity-90"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                </div>

                <!-- Status Text & Micro Progress -->
                <div
                    data-test="global-loader-status"
                    class="flex flex-col items-center gap-2.5"
                >
                    <span
                        class="text-[15px] font-medium tracking-tight text-foreground/90 sm:text-base"
                    >
                        {{ message || 'Signing in...' }}
                    </span>

                    <div
                        class="h-[2px] w-24 overflow-hidden rounded-full bg-foreground/10"
                        role="progressbar"
                        :aria-valuenow="progress"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-brand transition-[width] duration-200 ease-out"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Force Inter font family on the global loader */
html[data-font-preset] .global-loader.font-sans,
html[data-font-preset] .global-loader.font-sans * {
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        'Helvetica Neue',
        Arial,
        sans-serif !important;
}
</style>
