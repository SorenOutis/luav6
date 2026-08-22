<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useMobile } from '@/composables/useMobile';
import { getTourStatus, setTourStatus } from '@/lib/onboarding';
import type { OnboardingProps, TourStep } from '@/lib/onboarding';

/**
 * OnboardingTour — a lightweight spotlight walkthrough.
 *
 * - Persists completion on the user's account (and in localStorage for an
 *   instant/offline result), so a tour that was finished *or* skipped never
 *   auto-starts again — on any device, browser or after clearing site data.
 * - Steps whose `target` element is missing or hidden are skipped silently,
 *   so conditional UI (daily reward card, section tabs…) never breaks a tour.
 * - Uses theme tokens (bg-card / text-foreground / primary) so it renders
 *   correctly in every theme preset, light or dark.
 * - Honors `prefers-reduced-motion` and the shared low-end-device heuristics:
 *   all transitions collapse to 0ms and scrolling becomes instant.
 */

const props = withDefaults(
    defineProps<{
        /** Unique id for this tour, e.g. 'dashboard'. */
        tourId: string;
        steps: TourStep[];
        /**
         * Per-user scope. Defaults to the authenticated user's public_id so
         * shared devices onboard each account once.
         */
        scope?: string;
        /** The tour only auto-starts once this flips to true. */
        canStart?: boolean;
        /** Delay (ms) between canStart and the tour appearing. */
        startDelay?: number;
    }>(),
    {
        scope: undefined,
        canStart: true,
        startDelay: 700,
    },
);

const emit = defineEmits<{
    start: [];
    finish: [];
    skip: [];
    'update:active': [value: boolean];
}>();

const page = usePage();
const { isMobile, prefersReducedMotion, isLowEndDevice } = useMobile();

/** All motion collapses on reduced-motion / low-end hardware. */
const instant = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

/** Account-level record shared by HandleInertiaRequests. */
const onboardingProps = computed<OnboardingProps | null>(
    () => (page.props as { onboarding?: OnboardingProps }).onboarding ?? null,
);

const resolvedScope = computed(() => {
    if (props.scope !== undefined) return props.scope;
    const user = (
        page.props as { auth?: { user?: { public_id?: string } | null } }
    ).auth?.user;
    return user?.public_id ?? '';
});

// ─── State ──────────────────────────────────────────────────────────────────
const active = ref(false);
const stepIndex = ref(0);
const activeSteps = ref<TourStep[]>([]);

interface SpotRect {
    top: number;
    left: number;
    width: number;
    height: number;
}
const spot = ref<SpotRect | null>(null);

const rootRef = ref<HTMLElement | null>(null);
const cardRef = ref<HTMLElement | null>(null);
const cardPos = ref<{ top: number; left: number } | null>(null);

const currentStep = computed<TourStep | null>(
    () => activeSteps.value[stepIndex.value] ?? null,
);
const isLastStep = computed(
    () => stepIndex.value >= activeSteps.value.length - 1,
);

// ─── Target resolution ──────────────────────────────────────────────────────
const findTarget = (target?: string): HTMLElement | null => {
    if (!target || typeof document === 'undefined') return null;
    // The same tour target may exist twice (e.g. a mobile and a desktop
    // variant of a control) — pick the first *visible* match.
    const candidates = document.querySelectorAll<HTMLElement>(
        `[data-tour="${target}"]`,
    );
    for (const el of candidates) {
        const rect = el.getBoundingClientRect();
        // display:none / zero-size elements count as missing.
        if (rect.width >= 2 && rect.height >= 2) return el;
    }
    return null;
};

const SPOT_PADDING = 8;

const measure = () => {
    const step = currentStep.value;
    if (!step) return;
    const el = findTarget(step.target);
    if (!el) {
        spot.value = null;
        cardPos.value = null;
        return;
    }
    const rect = el.getBoundingClientRect();
    spot.value = {
        top: rect.top - SPOT_PADDING,
        left: rect.left - SPOT_PADDING,
        width: rect.width + SPOT_PADDING * 2,
        height: rect.height + SPOT_PADDING * 2,
    };
    void nextTick(() => updateCardPos());
};

const updateCardPos = () => {
    // Mobile cards dock to the bottom via CSS — no measuring needed.
    if (isMobile.value || !spot.value) {
        cardPos.value = null;
        return;
    }
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const cardW = cardRef.value?.offsetWidth || 380;
    const cardH = cardRef.value?.offsetHeight || 220;
    const gap = 14;
    const margin = 16;

    let top = spot.value.top + spot.value.height + gap;
    if (top + cardH > vh - margin) {
        top = spot.value.top - cardH - gap;
    }
    if (top < margin) {
        top = Math.max(margin, Math.min(vh - cardH - margin, spot.value.top));
    }

    let left = spot.value.left + spot.value.width / 2 - cardW / 2;
    left = Math.max(margin, Math.min(vw - cardW - margin, left));

    cardPos.value = { top, left };
};

// ─── Frame-throttled reposition (scroll / resize while active) ─────────────
let rafId: number | null = null;
const scheduleMeasure = () => {
    if (rafId !== null) return;
    rafId = requestAnimationFrame(() => {
        rafId = null;
        if (active.value) measure();
    });
};

// While the browser smooth-scrolls a target into view no scroll event storm
// should be missed, so listeners are capture-phase and passive.
const bindListeners = () => {
    window.addEventListener('scroll', scheduleMeasure, {
        capture: true,
        passive: true,
    });
    window.addEventListener('resize', scheduleMeasure, { passive: true });
    window.addEventListener('keydown', onKeydown);
};

const unbindListeners = () => {
    window.removeEventListener('scroll', scheduleMeasure, { capture: true });
    window.removeEventListener('resize', scheduleMeasure);
    window.removeEventListener('keydown', onKeydown);
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
};

const onKeydown = (e: KeyboardEvent) => {
    if (!active.value) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        skip();
    } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
        e.preventDefault();
        next();
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        back();
    }
};

// Block page scrolling behind the overlay, but let the card itself scroll on
// short viewports.
const onScrollBlock = (e: Event) => {
    if (cardRef.value && e.composedPath().includes(cardRef.value)) return;
    e.preventDefault();
};

// ─── Navigation ─────────────────────────────────────────────────────────────
const goToStep = (index: number) => {
    stepIndex.value = index;
    void nextTick(() => {
        const step = currentStep.value;
        const el = findTarget(step?.target);
        if (el) {
            el.scrollIntoView({
                block: 'center',
                behavior: instant.value ? 'auto' : 'smooth',
            });
        }
        measure();
        // Keep the spotlight glued to the target while smooth-scroll settles.
        if (!instant.value && el) {
            let frames = 0;
            const settle = () => {
                if (!active.value || frames > 40) return;
                frames += 1;
                measure();
                requestAnimationFrame(settle);
            };
            requestAnimationFrame(settle);
        }
        cardRef.value?.focus({ preventScroll: true });
    });
};

const next = () => {
    if (isLastStep.value) {
        finish();
        return;
    }
    goToStep(stepIndex.value + 1);
};

const back = () => {
    if (stepIndex.value === 0) return;
    goToStep(stepIndex.value - 1);
};

// ─── Lifecycle ──────────────────────────────────────────────────────────────
let startTimer: ReturnType<typeof setTimeout> | null = null;
let hasStartedOnce = false;

const begin = () => {
    if (active.value || hasStartedOnce) return;
    // Resolve visible steps at start time; untargeted steps always render.
    const steps = props.steps.filter(
        (s) => !s.target || findTarget(s.target) !== null,
    );
    if (steps.length === 0) return;
    hasStartedOnce = true;
    activeSteps.value = steps;
    active.value = true;
    emit('start');
    emit('update:active', true);
    bindListeners();
    void nextTick(() => {
        rootRef.value?.addEventListener('wheel', onScrollBlock, {
            passive: false,
        });
        rootRef.value?.addEventListener('touchmove', onScrollBlock, {
            passive: false,
        });
    });
    goToStep(0);
};

const close = () => {
    active.value = false;
    spot.value = null;
    cardPos.value = null;
    unbindListeners();
    emit('update:active', false);
};

const finish = () => {
    setTourStatus(props.tourId, 'done', resolvedScope.value);
    close();
    emit('finish');
};

const skip = () => {
    setTourStatus(props.tourId, 'skipped', resolvedScope.value);
    close();
    emit('skip');
};

/** Already finished or skipped — on this device or on the account. */
const isResolved = () =>
    getTourStatus(props.tourId, resolvedScope.value, onboardingProps.value) !==
    null;

const maybeAutoStart = () => {
    if (typeof window === 'undefined') return;
    if (hasStartedOnce || active.value) return;
    if (isResolved()) return;
    if (startTimer) clearTimeout(startTimer);
    startTimer = setTimeout(() => {
        startTimer = null;
        if (props.canStart) begin();
    }, props.startDelay);
};

onMounted(() => {
    if (props.canStart) maybeAutoStart();
});

watch(
    () => props.canStart,
    (ok) => {
        if (ok) {
            maybeAutoStart();
        } else if (startTimer) {
            clearTimeout(startTimer);
            startTimer = null;
        }
    },
);

onBeforeUnmount(() => {
    if (startTimer) clearTimeout(startTimer);
    unbindListeners();
});

// Manual restart hook for future "replay tour" affordances.
defineExpose({ begin, skip, finish });

// ─── Styles ─────────────────────────────────────────────────────────────────
const spotStyle = computed(() => {
    if (!spot.value) return undefined;
    return {
        top: `${spot.value.top}px`,
        left: `${spot.value.left}px`,
        width: `${spot.value.width}px`,
        height: `${spot.value.height}px`,
        transitionDuration: instant.value ? '0ms' : '280ms',
    };
});

const cardStyle = computed(() => {
    if (isMobile.value || !cardPos.value) return undefined;
    return {
        top: `${cardPos.value.top}px`,
        left: `${cardPos.value.left}px`,
    };
});
</script>

<template>
    <Teleport to="body">
        <Transition name="ot-fade" :css="!instant">
            <div
                v-if="active"
                ref="rootRef"
                class="onboarding-tour fixed inset-0 z-[80]"
                data-testid="onboarding-tour"
            >
                <!-- Full dim for untargeted (welcome / outro) steps -->
                <div
                    v-if="!spot"
                    class="absolute inset-0 bg-black/60"
                    aria-hidden="true"
                />

                <!-- Spotlight: one element + a huge box-shadow dims the rest.
                     Far cheaper than SVG masks or backdrop filters. -->
                <div
                    v-else
                    class="ot-spotlight absolute rounded-2xl"
                    :style="spotStyle"
                    aria-hidden="true"
                />

                <!-- Step card. Keep the element mounted between steps on
                     mobile. Replacing a fixed, scrollable dialog during a
                     touch click can leave WebKit waiting on the outgoing
                     transition, so the next card never gets inserted. -->
                <Transition name="ot-card" :css="!instant" mode="out-in">
                    <div
                        :key="
                            isMobile
                                ? 'mobile-tour-card'
                                : (currentStep?.id ?? stepIndex)
                        "
                        ref="cardRef"
                        tabindex="-1"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="`ot-title-${tourId}`"
                        class="ot-card fixed flex max-h-[70vh] w-[calc(100vw-1.5rem)] flex-col overflow-y-auto rounded-2xl border border-border/70 bg-card p-4 text-card-foreground shadow-2xl outline-none sm:absolute sm:w-[380px] sm:p-5"
                        :class="[
                            isMobile || !cardPos
                                ? spot
                                    ? 'inset-x-3 mx-auto max-w-md sm:inset-auto'
                                    : 'inset-x-3 top-1/2 mx-auto max-w-md -translate-y-1/2 sm:left-1/2 sm:w-[380px] sm:-translate-x-1/2'
                                : '',
                            // Hide the card for the single frame between the
                            // spotlight appearing and its position resolving
                            // (desktop only — mobile docks via CSS).
                            !isMobile && spot && !cardPos ? 'opacity-0' : '',
                        ]"
                        :style="[
                            cardStyle ?? {},
                            isMobile && spot
                                ? {
                                      bottom: 'calc(4.75rem + env(safe-area-inset-bottom, 0px))',
                                  }
                                : {},
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p
                                class="text-[11px] font-semibold tracking-wide text-primary uppercase"
                            >
                                {{ stepIndex + 1 }} of {{ activeSteps.length }}
                            </p>
                            <button
                                type="button"
                                class="-mt-1 -mr-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                aria-label="Skip tour"
                                data-testid="onboarding-close"
                                @click="skip"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <h2
                            :id="`ot-title-${tourId}`"
                            class="mt-0.5 text-[17px] font-semibold tracking-tight text-foreground sm:text-lg"
                        >
                            {{ currentStep?.title }}
                        </h2>
                        <p
                            class="mt-1.5 text-[13px] leading-5 text-muted-foreground sm:text-sm sm:leading-6"
                        >
                            {{ currentStep?.body }}
                        </p>

                        <!-- Progress dots -->
                        <div
                            class="mt-3.5 flex items-center gap-1.5"
                            aria-hidden="true"
                        >
                            <span
                                v-for="(s, i) in activeSteps"
                                :key="s.id"
                                class="h-1.5 rounded-full transition-all"
                                :class="
                                    i === stepIndex
                                        ? 'w-5 bg-primary'
                                        : 'w-1.5 bg-muted-foreground/25'
                                "
                                :style="{
                                    transitionDuration: instant
                                        ? '0ms'
                                        : '200ms',
                                }"
                            />
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between gap-2"
                        >
                            <button
                                type="button"
                                class="min-h-10 rounded-full px-3 text-[13px] font-medium text-muted-foreground transition-colors hover:text-foreground"
                                data-testid="onboarding-skip"
                                @click="skip"
                            >
                                Skip tour
                            </button>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="stepIndex > 0"
                                    type="button"
                                    class="min-h-10 rounded-full border border-border/70 bg-background px-4 text-[13px] font-medium text-foreground transition-colors hover:bg-muted"
                                    data-testid="onboarding-back"
                                    @click="back"
                                >
                                    Back
                                </button>
                                <button
                                    type="button"
                                    class="min-h-10 rounded-full bg-primary px-5 text-[13px] font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                                    data-testid="onboarding-next"
                                    @click="next"
                                >
                                    {{ isLastStep ? 'Done' : 'Next' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.ot-spotlight {
    /* A single spread shadow dims everything around the target — no masks,
       no backdrop-filter, so low-end GPUs stay happy. */
    box-shadow:
        0 0 0 2px hsl(0 0% 100% / 0.28),
        0 0 0 200vmax rgb(8 8 12 / 0.6);
    transition-property: top, left, width, height;
    transition-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
    pointer-events: none;
}

.ot-fade-enter-active,
.ot-fade-leave-active {
    transition: opacity 220ms ease;
}
.ot-fade-enter-from,
.ot-fade-leave-to {
    opacity: 0;
}

.ot-card-enter-active {
    transition:
        opacity 220ms ease,
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}
.ot-card-leave-active {
    transition: opacity 120ms ease;
}
.ot-card-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.ot-card-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .ot-spotlight,
    .ot-card-enter-active,
    .ot-card-leave-active,
    .ot-fade-enter-active,
    .ot-fade-leave-active {
        transition: none !important;
    }
}
</style>
