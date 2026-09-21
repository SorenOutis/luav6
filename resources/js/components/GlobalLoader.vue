<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
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

    // Entrance: gentle scale & fade
    if (contentWrap.value) {
        gsap.set(contentWrap.value, { y: 12, opacity: 0 });
        gsap.to(contentWrap.value, {
            y: 0,
            opacity: 1,
            duration: prefersReducedMotion ? 0.3 : 0.55,
            ease: 'power2.out',
        });
    }

    // Realistic progress: fast to 70, slow to 95, jump to 100 on pendingHide
    const duration = prefersReducedMotion ? 0.6 : 1.8;
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
        y: -6,
        duration: prefersReducedMotion ? 0.15 : fast ? 0.25 : 0.45,
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
        class="global-loader fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-background font-sans text-foreground selection:bg-primary/20"
        style="display: none"
    >
        <!-- Background subtle ambient lighting -->
        <div
            class="pointer-events-none absolute inset-0 -z-10 flex items-center justify-center"
            aria-hidden="true"
        >
            <div
                class="h-[380px] w-[380px] rounded-full bg-[#D97757]/[0.06] blur-[90px] sm:h-[480px] sm:w-[480px]"
            ></div>
        </div>

        <div
            ref="contentWrap"
            data-test="global-loader-editorial"
            class="relative flex w-full max-w-lg flex-col items-center px-5 py-8 sm:px-8"
        >
            <!-- Upper-left brand treatment (Responsive & clean) -->
            <div class="absolute top-8 left-6 flex flex-col items-start gap-1">
                <PublicBrandMark :logo-url="brandLogoUrl" size="loader" />
                <span
                    v-if="branding.tagline"
                    class="ml-10 max-w-[13rem] truncate text-[10px] font-medium tracking-[0.16em] text-muted-foreground/60 uppercase"
                >
                    {{ branding.tagline }}
                </span>
            </div>

            <!-- Elevated Central Loading Hub -->
            <main class="w-full pt-16 sm:pt-14">
                <section
                    class="surface-card relative flex w-full flex-col items-center rounded-2xl border border-border/80 bg-card/90 p-6 text-center shadow-xl backdrop-blur-md sm:p-8 md:p-10"
                >
                    <!-- Kicker -->
                    <p
                        class="text-[11px] font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                    >
                        LSI / GETTING READY
                    </p>

                    <!-- Mascot with glow -->
                    <div
                        data-test="global-loader-fox"
                        class="global-loader__fox relative z-10 mt-5 transition-transform duration-300 hover:scale-105"
                    >
                        <FoxCompanion
                            mascot="welcome"
                            :size="150"
                            :show-message="false"
                            :label="
                                isTerminating
                                    ? 'Echo signing out'
                                    : 'Echo getting things ready'
                            "
                        />
                    </div>

                    <!-- Headline & Subtitle -->
                    <h1
                        class="mt-4 font-serif text-3xl font-semibold tracking-[-0.04em] text-foreground sm:text-4xl"
                    >
                        {{
                            isTerminating
                                ? 'See you soon.'
                                : 'Getting things ready.'
                        }}
                    </h1>
                    <p
                        class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        {{
                            isTerminating
                                ? 'Echo will be here when you come back.'
                                : 'Echo is preparing your next step.'
                        }}
                    </p>

                    <!-- Progress Status Area -->
                    <div
                        data-test="global-loader-status"
                        class="mt-7 w-full border-t border-border/60 pt-4 text-left"
                    >
                        <div
                            class="flex items-center justify-between gap-4 text-xs font-medium"
                        >
                            <span class="truncate text-foreground/80">
                                {{ message }}
                            </span>
                            <span
                                class="shrink-0 font-mono text-[11px] font-semibold text-[#D97757] tabular-nums"
                            >
                                {{ progress }}%
                            </span>
                        </div>

                        <!-- Tactile Progress Bar (Terracotta matching dashboard) -->
                        <div
                            class="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-secondary/80"
                            role="progressbar"
                            :aria-valuenow="progress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-[#D97757] transition-[width] duration-200 ease-out"
                                :style="{ width: `${progress}%` }"
                            ></div>
                        </div>

                        <!-- Pulse status indicator -->
                        <div
                            class="mt-3 flex items-center justify-between text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#D97757]"
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
                            <span class="text-muted-foreground/60">
                                LSI Learning Systems
                            </span>
                        </div>
                    </div>
                </section>
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
