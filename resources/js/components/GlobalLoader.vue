<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import PublicBrandMark from '@/components/PublicBrandMark.vue';
import { useLoader } from '@/composables/useLoader';
import type { SchoolBranding } from '@/types/branding';

const props = withDefaults(
    defineProps<{
        show: boolean;
        minDisplayMs?: number;
    }>(),
    {
        minDisplayMs: 600,
    },
);

const { pendingHide, hide, message } = useLoader();

const page = usePage();
const branding = computed<SchoolBranding>(
    () => page.props.schoolBranding ?? {},
);
const brandLogoUrl = computed(() => branding.value.logoUrl || null);

const loaderContainer = ref<HTMLElement | null>(null);
const contentWrap = ref<HTMLElement | null>(null);
const progress = ref(0);

const isTerminating = computed(() => {
    const m = message.value.toLowerCase();
    return m.includes('signing out') || m.includes('terminating');
});

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
    if (props.show) startEntrance();
});

onBeforeUnmount(() => {
    gsap.killTweensOf(progressProxy);
    gsap.killTweensOf(loaderContainer.value);
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
    gsap.killTweensOf(loaderContainer.value);

    progressDone = false;
    progress.value = 0;
    progressProxy.val = 0;
    shownAt = performance.now();

    gsap.set(loaderContainer.value, { display: 'flex', autoAlpha: 1 });
    setInertOnSiblings(true);

    // Entrance: fade + slight slide up
    if (contentWrap.value) {
        gsap.set(contentWrap.value, { y: 16, opacity: 0 });
        gsap.to(contentWrap.value, {
            y: 0,
            opacity: 1,
            duration: prefersReducedMotion ? 0.3 : 0.6,
            ease: 'power2.out',
        });
    }

    // Realistic progress: fast to 70, slow to 95, jump to 100 on pendingHide
    const duration = prefersReducedMotion ? 0.6 : 2.0;
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
                            duration: 0.35,
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
    gsap.to(loaderContainer.value, {
        autoAlpha: 0,
        y: -8,
        duration: prefersReducedMotion ? 0.15 : fast ? 0.3 : 0.5,
        ease: 'power2.in',
        onComplete: () => {
            gsap.set(loaderContainer.value, { display: 'none', y: 0 });
            setInertOnSiblings(false);
            hide();
        },
    });
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
        class="global-loader fixed inset-0 z-[9999] bg-background font-sans text-foreground"
        style="display: none"
    >
        <div
            ref="contentWrap"
            class="relative flex h-full w-full flex-col items-center justify-center px-6 py-8 sm:px-10 sm:py-10"
        >
            <!-- Upper-left brand treatment -->
            <div
                class="absolute top-8 left-6 flex flex-col items-start gap-2 sm:top-10 sm:left-10"
            >
                <PublicBrandMark :logo-url="brandLogoUrl" size="loader" />
                <span
                    v-if="branding.tagline"
                    class="ml-12 max-w-[14rem] truncate text-[10px] font-medium tracking-[0.18em] text-muted-foreground/50 uppercase"
                >
                    {{ branding.tagline }}
                </span>
            </div>

            <!-- Loading Status -->
            <div class="flex w-full max-w-md flex-col items-center gap-4">
                <div
                    class="flex w-full items-center justify-between text-xs font-medium text-muted-foreground/60"
                >
                    <span>{{ message }}</span>
                    <span class="tabular-nums">{{ progress }}%</span>
                </div>

                <!-- Progress bar -->
                <div
                    class="h-1 w-full overflow-hidden rounded-full bg-border/30"
                    role="progressbar"
                    :aria-valuenow="progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-[width] duration-200"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>

                <!-- Subtle pulse dot -->
                <div class="flex items-center gap-2">
                    <div
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary/60"
                    ></div>
                    <span
                        class="text-[10px] font-medium tracking-widest text-muted-foreground/30 uppercase"
                    >
                        {{ isTerminating ? 'Cleaning up...' : 'Loading...' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Force Inter on the global loader regardless of dashboard font presets.
   Uses higher specificity than :root[data-font-preset] .font-sans (0-3-1 vs 0-3-0).
   The * selector ensures child elements with font-sans are also overridden. */
html[data-font-preset] .global-loader.font-sans,
html[data-font-preset] .global-loader.font-sans * {
    font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
}
</style>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    .animate-pulse {
        animation: none !important;
    }
}
</style>
