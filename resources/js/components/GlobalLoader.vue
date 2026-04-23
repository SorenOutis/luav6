<script setup lang="ts">
import gsap from 'gsap';
import { Terminal, Command } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount, watch, computed, type VNodeRef } from 'vue';
import { useLoader, LOADER_MESSAGES } from '@/composables/useLoader';

const props = withDefaults(defineProps<{
    show: boolean;
    title?: string;
    minDisplayMs?: number;
}>(), {
    title: 'KOAMISHIN.ORG',
    minDisplayMs: 600,
});

const { pendingHide, hide, message } = useLoader();

const DEV = import.meta.env.DEV;
const log = (...args: unknown[]) => { if (DEV) console.log(...args); };

const loaderContainer = ref<HTMLElement | null>(null);
const structuralLines = ref<HTMLElement[]>([]);
const letterEls = ref<HTMLElement[]>([]);
const progress = ref(0);

const isTerminating = computed(() => message.value === LOADER_MESSAGES.TERMINATING);
const titleChars = computed(() => Array.from(props.title));

// Reduced-motion preference
const prefersReducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// Gates
let progressDone = false;
let shownAt = 0;
let entranceTl: gsap.core.Timeline | null = null;
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

const setStructuralLineRef: VNodeRef = (el) => {
    if (el instanceof HTMLElement && !structuralLines.value.includes(el)) {
        structuralLines.value.push(el);
    }
};

const tryExit = () => {
    log(`[GlobalLoader] tryExit. progressDone: ${progressDone}, pendingHide: ${pendingHide.value}`);
    if (!(progressDone && pendingHide.value)) return;

    const elapsed = performance.now() - shownAt;
    const wait = Math.max(0, props.minDisplayMs - elapsed);
    if (wait > 0) {
        setTimeout(startExit, wait);
    } else {
        startExit();
    }
};

onMounted(() => {
    letterEls.value = [];
    gsap.set(loaderContainer.value, { y: '-100%', display: 'none' });
    if (props.show) startEntrance();
});

onBeforeUnmount(() => {
    entranceTl?.kill();
    gsap.killTweensOf(progressProxy);
    gsap.killTweensOf(loaderContainer.value);
    setInertOnSiblings(false);
});

watch(() => props.show, (newVal) => {
    log('[GlobalLoader] show ->', newVal);
    if (newVal) {
        startEntrance();
    } else if (loaderContainer.value && loaderContainer.value.style.display !== 'none') {
        log('[GlobalLoader] forcing exit');
        startExit(true);
    }
});

const startEntrance = () => {
    log('[GlobalLoader] Entrance');

    // Kill any previous tweens/timelines to avoid overlapping state
    entranceTl?.kill();
    gsap.killTweensOf(progressProxy);
    gsap.killTweensOf(loaderContainer.value);

    // Reset state (NOT the ref arrays — Vue only re-populates function refs on re-render)
    progressDone = false;
    progress.value = 0;
    progressProxy.val = 0;
    shownAt = performance.now();

    const tl = gsap.timeline();
    entranceTl = tl;

    gsap.set(loaderContainer.value, { display: 'flex', y: '0%', opacity: 1 });
    setInertOnSiblings(true);

    if (prefersReducedMotion) {
        gsap.set('.loader-reveal', { y: 0, opacity: 1 });
        gsap.set(structuralLines.value, { scaleX: 1, scaleY: 1 });
        gsap.set(letterEls.value, { y: '0%', opacity: 1 });
    } else {
        gsap.set('.loader-reveal', { y: 20, opacity: 0 });
        gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
        if (!isTerminating.value) {
            gsap.set(letterEls.value, { y: '110%', opacity: 0 });
        }

        if (isTerminating.value) {
            tl.to(structuralLines.value, {
                scaleX: 1, scaleY: 1, stagger: 0.06, duration: 0.35, ease: 'power2.out',
            }).to('.loader-reveal', {
                y: 0, opacity: 1, stagger: 0.06, duration: 0.35, ease: 'power2.out',
            }, '-=0.2');
        } else {
            tl.to(structuralLines.value, {
                scaleX: 1, scaleY: 1, stagger: 0.1, duration: 0.8, ease: 'power4.inOut',
            }).to(letterEls.value, {
                y: '0%', opacity: 1, stagger: 0.06, duration: 0.9, ease: 'expo.out',
            }, '-=0.3').to('.loader-reveal', {
                y: 0, opacity: 1, stagger: 0.1, duration: 0.8, ease: 'expo.out',
            }, '-=0.4');
        }
    }

    // Realistic progress: fast to 70, slow to 95, jump to 100 on pendingHide.
    const duration = prefersReducedMotion ? 0.6 : 2.2;
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
                log('[GlobalLoader] progress 100%');
                progressDone = true;
                tryExit();
            } else {
                // Wait for pendingHide, then jump to 100
                const stopWatch = watch(pendingHide, (v) => {
                    if (!v) return;
                    stopWatch();
                    gsap.to(progressProxy, {
                        val: 100,
                        duration: 0.35,
                        ease: 'power2.out',
                        onUpdate: () => { progress.value = Math.floor(progressProxy.val); },
                        onComplete: () => {
                            progressDone = true;
                            tryExit();
                        },
                    });
                }, { immediate: true });
            }
        },
    });
};

const startExit = (fast: boolean = false) => {
    log(`[GlobalLoader] Exit (${fast ? 'FAST' : 'NORMAL'})`);

    if (fast) {
        gsap.killTweensOf(progressProxy);
        progress.value = 100;
        progressProxy.val = 100;
    }

    gsap.to(loaderContainer.value, {
        y: '-100%',
        duration: prefersReducedMotion ? 0.2 : (fast ? 0.6 : 1.2),
        ease: fast ? 'expo.in' : 'expo.inOut',
        delay: fast || prefersReducedMotion ? 0 : 0.4,
        onComplete: () => {
            log('[GlobalLoader] Exit complete');
            gsap.set(loaderContainer.value, { display: 'none' });
            setInertOnSiblings(false);
            hide();
        },
    });
};

// Gate 2: server signals done
watch(pendingHide, (isPending) => {
    if (isPending) {
        log('[GlobalLoader] Server done');
        tryExit();
    }
});
</script>

<template>
    <div
        ref="loaderContainer"
        role="status"
        aria-live="polite"
        aria-busy="true"
        :aria-label="`${message} — ${progress}%`"
        class="global-loader fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-background font-sans text-foreground selection:bg-primary/20 overflow-hidden"
        style="display: none;"
    >
        <!-- Structural Grid Background -->
        <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06]" aria-hidden="true">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 80px 80px;"></div>
        </div>

        <!-- Structural Frame Lines -->
        <div
            :ref="setStructuralLineRef"
            aria-hidden="true"
            class="fixed left-1/2 -translate-x-1/2 top-0 bottom-0 w-px bg-border/20 z-0 origin-top"
        ></div>
        <div
            :ref="setStructuralLineRef"
            aria-hidden="true"
            class="fixed left-0 right-0 top-1/2 -translate-y-1/2 h-px bg-border/20 z-0 origin-left"
        ></div>

        <main class="relative z-10 flex flex-col items-center gap-12 px-6">
            <!-- Monolithic Branding -->
            <div class="flex flex-col items-center">
                <h1
                    v-if="!isTerminating"
                    :aria-label="title"
                    class="flex items-end overflow-hidden text-[12vw] md:text-8xl lg:text-[10rem] font-black tracking-[-0.04em] leading-none uppercase select-none"
                >
                    <span
                        v-for="(char, i) in titleChars"
                        :key="i"
                        :ref="(el) => { if (el) letterEls[i] = el as HTMLElement }"
                        aria-hidden="true"
                        class="loader-letter inline-block"
                    >{{ char }}</span>
                </h1>
                <div
                    v-else
                    aria-hidden="true"
                    class="h-[12vw] min-h-[72px] md:h-28 lg:h-32"
                ></div>
            </div>

            <!-- Initialization Status -->
            <div class="flex flex-col items-center gap-6 w-full max-w-xs">
                <div class="flex items-center justify-between w-full text-[9px] font-black tracking-[0.4em] uppercase text-muted-foreground/60 loader-reveal">
                    <span>{{ message }}</span>
                    <span aria-hidden="true">{{ progress }}%</span>
                </div>

                <div
                    class="h-px w-full bg-border/20 overflow-hidden loader-reveal"
                    role="progressbar"
                    :aria-valuenow="progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="loader-bar h-full bg-primary"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>

                <div class="flex items-center gap-3 opacity-30 loader-reveal" aria-hidden="true">
                    <div class="h-1 w-1 rounded-full bg-primary animate-pulse"></div>
                    <span class="text-[8px] font-black uppercase tracking-[0.5em] animate-pulse">
                        {{ isTerminating ? 'Safely Disconnecting Active Node...' : 'Establishing Node Connectivity...' }}
                    </span>
                </div>
            </div>
        </main>

        <!-- Environment Metadata Footer -->
        <div
            class="fixed bottom-12 left-1/2 -translate-x-1/2 flex items-center gap-8 opacity-20 text-[9px] font-bold tracking-[0.3em] uppercase loader-reveal"
            aria-hidden="true"
        >
            <span class="flex items-center gap-2"><Terminal class="w-3 h-3" /> System_Root</span>
            <span class="flex items-center gap-2"><Command class="w-3 h-3" /> Core_v6.4</span>
        </div>
    </div>
</template>

<style scoped>
.global-loader,
.loader-letter,
.loader-reveal {
    will-change: transform, opacity;
}
.loader-bar {
    will-change: width;
}
@media (prefers-reduced-motion: reduce) {
    .animate-pulse {
        animation: none !important;
    }
}
</style>
