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
        class="global-loader fixed inset-0 z-[9999] bg-[#f8f7f2] font-sans text-[#17201f] dark:bg-background dark:text-foreground"
        style="display: none"
    >
        <div
            ref="contentWrap"
            data-test="global-loader-editorial"
            class="relative mx-auto flex min-h-full w-full max-w-[1440px] flex-col px-6 pt-28 pb-10 sm:px-10 sm:pt-32 sm:pb-14 lg:px-16"
        >
            <!-- Upper-left brand treatment -->
            <div
                class="absolute top-8 left-6 flex flex-col items-start gap-2 sm:top-10 sm:left-10 lg:left-16"
            >
                <PublicBrandMark :logo-url="brandLogoUrl" size="loader" />
                <span
                    v-if="branding.tagline"
                    class="ml-12 max-w-[14rem] truncate text-[10px] font-medium tracking-[0.18em] text-[#17201f]/45 uppercase dark:text-muted-foreground/50"
                >
                    {{ branding.tagline }}
                </span>
            </div>

            <main
                class="grid flex-1 content-center items-center gap-14 lg:grid-cols-[minmax(0,1.05fr)_minmax(280px,0.75fr)] lg:gap-24"
            >
                <section class="max-w-xl">
                    <p
                        class="mb-5 text-[10px] font-semibold tracking-[0.22em] text-primary uppercase"
                    >
                        LSI / SYSTEM NOTE
                    </p>
                    <h1
                        class="max-w-lg font-serif text-4xl leading-[1.02] tracking-[-0.045em] text-[#17201f] sm:text-6xl dark:text-foreground"
                    >
                        Making room for what comes next.
                    </h1>
                    <p
                        class="mt-6 max-w-md text-sm leading-7 text-[#17201f]/60 sm:text-base dark:text-muted-foreground"
                    >
                        Your workspace is getting ready. We are bringing the
                        next clear step into view.
                    </p>

                    <div
                        data-test="global-loader-status"
                        class="mt-12 max-w-md border-t border-[#17201f]/20 pt-4 dark:border-border/40"
                    >
                        <div
                            class="flex items-center justify-between gap-4 text-xs font-medium"
                        >
                            <span
                                class="truncate text-[#17201f]/65 dark:text-muted-foreground/70"
                            >
                                {{ message }}
                            </span>
                            <span
                                class="shrink-0 font-mono text-[11px] text-[#17201f]/55 tabular-nums dark:text-muted-foreground/60"
                            >
                                {{ progress }}%
                            </span>
                        </div>

                        <!-- Editorial progress rule -->
                        <div
                            class="mt-5 h-px w-full bg-[#17201f]/15 dark:bg-border/50"
                            role="progressbar"
                            :aria-valuenow="progress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full bg-primary transition-[width] duration-200"
                                :style="{ width: `${progress}%` }"
                            ></div>
                        </div>

                        <div
                            class="mt-4 flex items-center gap-2 text-[10px] font-medium tracking-[0.18em] text-[#17201f]/40 uppercase dark:text-muted-foreground/45"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-primary/70"
                                :class="{
                                    'animate-pulse': !prefersReducedMotion,
                                }"
                            ></span>
                            <span>
                                {{
                                    isTerminating
                                        ? 'Cleaning up...'
                                        : 'Loading...'
                                }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Note artifact, echoing the classroom workflow -->
                <aside
                    aria-label="Assessment workflow note"
                    class="relative mx-auto w-full max-w-sm lg:mt-10"
                >
                    <div
                        class="absolute -top-3 -right-3 h-10 w-10 rounded-full border border-primary/25 bg-primary/10"
                        aria-hidden="true"
                    ></div>
                    <div
                        class="relative rotate-[-2deg] border border-[#17201f]/15 bg-[#fffdf7] p-6 shadow-[0_18px_50px_rgba(23,32,31,0.08)] dark:border-border/40 dark:bg-card"
                    >
                        <div
                            class="flex items-center justify-between border-b border-[#17201f]/15 pb-4 text-[10px] font-semibold tracking-[0.2em] text-[#17201f]/45 uppercase dark:border-border/40 dark:text-muted-foreground/55"
                        >
                            <span>Today's note</span>
                            <span>01</span>
                        </div>
                        <div class="space-y-5 py-6">
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-[0.18em] text-primary uppercase"
                                >
                                    Assessment / feedback / next step
                                </p>
                                <p
                                    class="mt-3 font-serif text-2xl leading-tight tracking-[-0.03em] text-[#17201f] dark:text-foreground"
                                >
                                    Keep the signal. Lose the noise.
                                </p>
                            </div>
                            <div
                                class="space-y-3 text-xs leading-5 text-[#17201f]/60 dark:text-muted-foreground"
                            >
                                <div class="flex gap-3">
                                    <span
                                        class="font-mono text-[10px] text-primary/75"
                                        >01</span
                                    >
                                    <span
                                        >Read what the response is telling
                                        you.</span
                                    >
                                </div>
                                <div class="flex gap-3">
                                    <span
                                        class="font-mono text-[10px] text-primary/75"
                                        >02</span
                                    >
                                    <span>Choose the next useful move.</span>
                                </div>
                            </div>
                        </div>
                        <div
                            class="border-t border-[#17201f]/15 pt-4 text-[10px] font-medium tracking-[0.16em] text-[#17201f]/40 uppercase dark:border-border/40 dark:text-muted-foreground/45"
                        >
                            A clearer class is close.
                        </div>
                    </div>
                </aside>
            </main>
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
