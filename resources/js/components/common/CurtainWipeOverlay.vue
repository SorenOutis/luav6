<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Users, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Component } from 'vue';
import { useCurtainWipe } from '@/composables/useCurtainWipe';
import type { WipeDirection } from '@/composables/useCurtainWipe';

interface Props {
    title?: string;
    subtitle?: string;
    icon?: Component;
    expectedMountDirection?: WipeDirection;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Community Lounge',
    subtitle: 'Connecting to workspace lounge...',
    icon: () => Users,
    expectedMountDirection: undefined,
});

const emit = defineEmits<{
    (e: 'cancel'): void;
}>();

const overlayRef = ref<HTMLElement | null>(null);
const curtainRef = ref<HTMLElement | null>(null);
const glassCardRef = ref<HTMLElement | null>(null);
const showCancelAction = ref(false);
const isOverlayActive = ref(false);

let cancelTimer: ReturnType<typeof setTimeout> | null = null;
let safetyTimer: ReturnType<typeof setTimeout> | null = null;

const {
    isWiping,
    wipeTo: runWipeTo,
    handleMountWipe,
    dismissWipe,
} = useCurtainWipe();

const clearTimers = () => {
    if (cancelTimer) {
        clearTimeout(cancelTimer);
        cancelTimer = null;
    }
    if (safetyTimer) {
        clearTimeout(safetyTimer);
        safetyTimer = null;
    }
};

const wipeTo = (
    destinationUrl: string,
    direction: WipeDirection = 'to_community',
    onComplete?: () => void,
) => {
    clearTimers();
    showCancelAction.value = false;
    isOverlayActive.value = true;

    // Show graceful cancel action if transition exceeds 2.4s
    cancelTimer = setTimeout(() => {
        if (isOverlayActive.value) {
            showCancelAction.value = true;
        }
    }, 2400);

    // Safety fallback: auto-dismiss if stuck for 8s
    safetyTimer = setTimeout(() => {
        if (isOverlayActive.value) {
            handleCancel();
        }
    }, 8000);

    runWipeTo(
        destinationUrl,
        curtainRef.value,
        overlayRef.value,
        direction,
        onComplete,
    );
};

const handleCancel = () => {
    clearTimers();
    showCancelAction.value = false;
    isOverlayActive.value = false;
    router.cancel?.();
    dismissWipe(curtainRef.value, overlayRef.value);
    emit('cancel');
};

const dismiss = () => {
    clearTimers();
    showCancelAction.value = false;
    isOverlayActive.value = false;
    dismissWipe(curtainRef.value, overlayRef.value);
};

onMounted(() => {
    handleMountWipe(
        curtainRef.value,
        overlayRef.value,
        props.expectedMountDirection,
    );
});

onBeforeUnmount(() => {
    clearTimers();
    if (curtainRef.value) {
        import('gsap').then(({ gsap }) => {
            gsap.killTweensOf(curtainRef.value);
            if (glassCardRef.value) {
                gsap.killTweensOf(glassCardRef.value);
            }
        });
    }
});

defineExpose({
    isWiping,
    wipeTo,
    dismiss,
});
</script>

<template>
    <div
        ref="overlayRef"
        class="pointer-events-none fixed inset-0 z-[100] overflow-hidden"
        style="visibility: hidden; opacity: 0"
        aria-hidden="true"
        data-test="curtain-wipe-overlay"
    >
        <div
            ref="curtainRef"
            class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden bg-[#f6f6f8]/96 text-neutral-900 backdrop-blur-3xl transition-colors duration-300 dark:bg-[#0c0c0e]/96 dark:text-neutral-100"
            data-test="curtain-wipe-backdrop"
        >
            <!-- Ambient subtle depth wash -->
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_40%,rgba(217,119,87,0.06),transparent_70%)] dark:bg-[radial-gradient(ellipse_60%_50%_at_50%_40%,rgba(217,119,87,0.08),transparent_70%)]"
                aria-hidden="true"
            />

            <!-- VisionOS / macOS Sequoia Floating Glass Squircle Card -->
            <div
                ref="glassCardRef"
                class="relative mx-4 flex w-[90vw] max-w-[340px] flex-col items-center overflow-hidden rounded-[28px] border border-black/[0.08] bg-white/95 p-7 text-center shadow-[0_24px_50px_-12px_rgba(0,0,0,0.12),0_1px_0_0_rgba(255,255,255,1)_inset] backdrop-blur-2xl transition-colors duration-300 sm:max-w-[360px] sm:rounded-[32px] sm:p-8 dark:border-white/15 dark:bg-[#1c1c1e]/95 dark:shadow-[0_32px_70px_-16px_rgba(0,0,0,0.85),0_1px_0_0_rgba(255,255,255,0.08)_inset]"
                data-test="glass-card"
            >
                <!-- Squircle Icon Pod with Refined Apple Craft -->
                <div class="mb-4 flex items-center justify-center">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-[20px] border border-brand/20 bg-brand/[0.08] shadow-xs sm:h-16 sm:w-16 dark:border-brand/30 dark:bg-brand/[0.12]"
                    >
                        <component
                            :is="props.icon"
                            class="h-6 w-6 text-brand sm:h-7 sm:w-7"
                        />
                    </div>
                </div>

                <!-- Optical Typography: Title & Subtitle -->
                <h3
                    class="text-[17px] font-semibold tracking-[-0.015em] text-neutral-900 sm:text-[18px] dark:text-neutral-100"
                >
                    {{ props.title }}
                </h3>
                <p
                    class="mt-1 text-[13px] leading-normal font-normal text-neutral-500 dark:text-neutral-400"
                >
                    {{ props.subtitle }}
                </p>

                <!-- Apple Activity Indicator & Status -->
                <div class="mt-6 flex flex-col items-center gap-2.5">
                    <div
                        class="relative flex items-center justify-center"
                        aria-hidden="true"
                    >
                        <svg
                            class="h-5 w-5 animate-spin text-neutral-400 dark:text-neutral-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="9"
                                stroke="currentColor"
                                stroke-width="2.5"
                            />
                            <path
                                class="opacity-80"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                d="M12 3a9 9 0 0 1 9 9"
                            />
                        </svg>
                    </div>

                    <span
                        class="text-[12px] font-medium tracking-tight text-neutral-500 dark:text-neutral-400"
                    >
                        Connecting
                    </span>
                </div>

                <!-- Agency & Fallback: Cancel action when navigation is in-flight -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <button
                        v-if="showCancelAction"
                        type="button"
                        class="pointer-events-auto mt-4 inline-flex items-center gap-1.5 rounded-full border border-black/10 bg-neutral-100 px-3.5 py-1 text-[11px] font-medium text-neutral-600 transition-all hover:bg-neutral-200 hover:text-neutral-900 focus:outline-none focus-visible:ring-1 focus-visible:ring-black/20 active:scale-[0.97] dark:border-white/10 dark:bg-white/[0.08] dark:text-neutral-300 dark:hover:bg-white/15 dark:hover:text-white dark:focus-visible:ring-white/40"
                        aria-label="Cancel transition"
                        @click="handleCancel"
                    >
                        <X class="h-3 w-3" />
                        <span>Cancel</span>
                    </button>
                </transition>
            </div>
        </div>
    </div>
</template>
