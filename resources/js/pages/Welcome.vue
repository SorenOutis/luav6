<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import { Motion } from '@motionone/vue';

// Sub-components
import NeuralParticleNetwork from '@/components/welcome/NeuralParticleNetwork.vue';
import SystemTerminal from '@/components/welcome/SystemTerminal.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import LivePulse from '@/components/welcome/LivePulse.vue';
import EnvironmentPanel from '@/components/welcome/EnvironmentPanel.vue';
import SystemMetrics from '@/components/welcome/SystemMetrics.vue';
import ArchitectureStack from '@/components/welcome/ArchitectureStack.vue';
import DimensionalCore from '@/components/welcome/DimensionalCore.vue';
import FloatingPrisms from '@/components/welcome/FloatingPrisms.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import AudiencePathways from '@/components/welcome/AudiencePathways.vue';
import TrustReadiness from '@/components/welcome/TrustReadiness.vue';
import AppPreviewShowcase from '@/components/welcome/AppPreviewShowcase.vue';
import ReportSuite from '@/components/welcome/ReportSuite.vue';
import DemoVideoModal from '@/components/welcome/DemoVideoModal.vue';
import SeasonCountdown from '@/components/welcome/SeasonCountdown.vue';
import DemoQuiz from '@/components/welcome/DemoQuiz.vue';
import TechStackCarousel from '@/components/welcome/TechStackCarousel.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';

// Composables & Routes
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login, register } from '@/routes';

gsap.registerPlugin(ScrollTrigger);

interface ActiveSeason {
    name: string;
    startDate: string | null;
    endDate: string | null;
    showCountdown: boolean;
}

interface SchoolBranding {
    name?: string;
    tagline?: string;
    logoUrl?: string | null;
    accentColor?: string;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        totalUsers?: number;
        totalExams?: number;
        totalAssignments?: number;
        totalSubmissions?: number;
        activeSeason?: ActiveSeason | null;
        demoVideoUrl?: string | null;
        schoolBranding?: SchoolBranding;
    }>(),
    {
        canRegister: true,
        totalUsers: 0,
        totalExams: 0,
        totalAssignments: 0,
        totalSubmissions: 0,
        activeSeason: null,
        demoVideoUrl: null,
        schoolBranding: () => ({
            name: 'LSI Engine',
            tagline: 'Learning Systems Intelligence',
            logoUrl: null,
            accentColor: '#f59e0b',
        }),
    },
);

// Refs for GSAP
const mainContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const mouseGlow = ref<HTMLElement | null>(null);
const structuralLines = ref<HTMLElement[]>([]);
const ambientOrbs = ref<HTMLElement[]>([]);
const bootOverlay = ref<HTMLElement | null>(null);
const bootText = ref<HTMLElement[]>([]);
const terminalReveal = ref<HTMLElement | null>(null);

// State
const isCoarsePointer = ref(false);
const prefersReducedMotion = ref(false);
const isLoggingOut = ref(false);
const showBootOverlay = ref(true);
const isBooted = ref(false);
const isTerminalActive = ref(false);
const isDemoVideoOpen = ref(false);

const { isTransitioningTheme } = useAppearance();

const brandName = computed(() => props.schoolBranding?.name || 'LSI Engine');
const brandAccentColor = computed(() => props.schoolBranding?.accentColor || '#f59e0b');

// Interaction Modes
const syncInteractionModes = () => {
    isCoarsePointer.value = window.matchMedia('(pointer: coarse)').matches;
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

// Mouse Effects
const handleGlobalMouseMove = (e: MouseEvent) => {
    if (!mouseGlow.value || !backgroundGrid.value || isCoarsePointer.value || prefersReducedMotion.value) return;

    const { clientX, clientY } = e;
    const xPercent = clientX / window.innerWidth;
    const yPercent = clientY / window.innerHeight;

    gsap.to(mouseGlow.value, {
        x: clientX,
        y: clientY,
        duration: 1.2,
        ease: 'power3.out'
    });

    gsap.to(backgroundGrid.value, {
        x: (xPercent - 0.5) * 40,
        y: (yPercent - 0.5) * 40,
        duration: 1.5,
        ease: 'power2.out'
    });
};

const handleMagnetic = (e: MouseEvent) => {
    if (isCoarsePointer.value || prefersReducedMotion.value) return;
    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    gsap.to(btn, { x: x * 0.4, y: y * 0.4, duration: 0.3, ease: 'power2.out' });
};

const resetMagnetic = (e: MouseEvent) => {
    const btn = e.currentTarget as HTMLElement;
    gsap.to(btn, { x: 0, y: 0, duration: 0.5, ease: 'elastic.out(1, 0.3)' });
};

const openDemoVideo = () => {
    isDemoVideoOpen.value = true;
};

const closeDemoVideo = () => {
    isDemoVideoOpen.value = false;
};

// Text Scramble Utility
const SCRAMBLE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*_-+';
const scrambleText = (el: HTMLElement) => {
    const original = el.dataset.scramble || el.textContent || '';
    const totalDuration = 600;
    const chars = original.split('');
    const resolved = new Array(chars.length).fill(false);
    const start = performance.now();

    const tick = (now: number) => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / totalDuration, 1);
        const resolveCount = Math.floor(progress * chars.length);
        for (let i = 0; i < resolveCount; i++) resolved[i] = true;

        el.textContent = chars.map((c, i) => {
            if (c === ' ') return ' ';
            if (resolved[i]) return c;
            return SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
        }).join('');

        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = original;
    };
    requestAnimationFrame(tick);
};

// Scroll-reveal system: any element with [data-reveal] gets a slick fade+slide+blur reveal.
// Variants via attribute value: 'up' (default) | 'down' | 'left' | 'right' | 'fade' | 'scale'.
// Optional: data-reveal-stagger (seconds) staggers immediate children instead of the element.
// Optional: data-reveal-delay (seconds), data-reveal-duration (seconds).
const initScrollReveal = () => {
    const reduced = prefersReducedMotion.value;
    const els = document.querySelectorAll<HTMLElement>('[data-reveal]');

    els.forEach((el) => {
        const variant = (el.dataset.reveal || 'up').toLowerCase();
        const staggerAttr = parseFloat(el.dataset.revealStagger || '0');
        const delay = parseFloat(el.dataset.revealDelay || '0');
        const duration = parseFloat(el.dataset.revealDuration || '1.1');

        const offsetMap: Record<string, { x?: number; y?: number; scale?: number }> = {
            up: { y: 40 },
            down: { y: -40 },
            left: { x: -50 },
            right: { x: 50 },
            fade: {},
            scale: { scale: 0.92 },
        };
        const offset = offsetMap[variant] ?? offsetMap.up;

        const targets: Element[] | HTMLElement = staggerAttr > 0 && el.children.length > 0
            ? Array.from(el.children)
            : el;

        const fromVars: gsap.TweenVars = {
            opacity: 0,
            ...offset,
            filter: reduced ? 'none' : 'blur(12px)',
            willChange: 'transform, opacity, filter',
        };

        const toVars: gsap.TweenVars = {
            opacity: 1,
            x: 0,
            y: 0,
            scale: 1,
            filter: 'blur(0px)',
            duration: reduced ? 0.4 : duration,
            ease: 'expo.out',
            delay,
            stagger: staggerAttr > 0 ? staggerAttr : 0,
            clearProps: 'willChange,filter',
            scrollTrigger: {
                trigger: el,
                start: 'top 85%',
                once: true,
            },
        };

        gsap.fromTo(targets, fromVars, toVars);
    });
};

// Directional reveal: a section is visible only while it overlaps the viewport.
//   - Scrolled past it (above viewport)  -> hide UP
//   - Scrolled before it (below viewport) -> hide DOWN
//   - Re-entering from either edge        -> show
const initDirectionalReveal = () => {
    if (prefersReducedMotion.value || !mainContainer.value) return;

    const main = mainContainer.value.querySelector('main');
    if (!main) return;

    const sections = Array.from(main.children) as HTMLElement[];
    const viewportH = window.innerHeight;

    sections.forEach((section) => {
        // Skip tiny / non-visual wrappers
        if (section.offsetHeight < 40) return;

        gsap.set(section, { willChange: 'transform, opacity, filter' });

        const hideUp = () => {
            gsap.to(section, {
                opacity: 0, y: -40, filter: 'blur(10px)',
                duration: 0.55, ease: 'power2.in', overwrite: 'auto',
            });
        };

        const hideDown = () => {
            gsap.to(section, {
                opacity: 0, y: 40, filter: 'blur(10px)',
                duration: 0.55, ease: 'power2.in', overwrite: 'auto',
            });
        };

        const show = () => {
            gsap.to(section, {
                opacity: 1, y: 0, filter: 'blur(0px)',
                duration: 0.7, ease: 'expo.out', overwrite: 'auto',
            });
        };

        // Pre-hide sections that start below the viewport so initial scroll-down reveals them.
        const rect = section.getBoundingClientRect();
        if (rect.top > viewportH * 0.95) {
            gsap.set(section, { opacity: 0, y: 40, filter: 'blur(10px)' });
        }

        // Top edge: hide/show as section leaves/re-enters past the top of the viewport.
        ScrollTrigger.create({
            trigger: section,
            start: 'bottom top+=40',
            end: 'bottom top',
            onLeave: hideUp,        // scrolled down past the section
            onEnterBack: show,      // scrolled back up into the section
        });

        // Bottom edge: hide/show as section enters/leaves past the bottom of the viewport.
        ScrollTrigger.create({
            trigger: section,
            start: 'top bottom-=40',
            end: 'top bottom',
            onEnter: show,          // scrolled down into the section from below
            onLeaveBack: hideDown,  // scrolled up so the section drops below the viewport
        });
    });
};

const initScrambleElements = () => {
    const els = document.querySelectorAll<HTMLElement>('[data-scramble]');
    els.forEach(el => {
        el.dataset.scramble = el.textContent?.trim() || '';
        ScrollTrigger.create({
            trigger: el,
            start: 'top 88%',
            once: true,
            onEnter: () => scrambleText(el),
        });
    });
};

// Boot Overlay Content
const bootMessage = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'SESSION TERMINATED';
    }
    return brandName.value.toUpperCase();
});

const bootSubtext = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'Safely Terminating Active Node Sessions...';
    }
    return 'Booting System Components...';
});

let gsapCtx: gsap.Context | null = null;
let removeMediaListeners = () => {};

onMounted(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        isLoggingOut.value = true;
    }

    syncInteractionModes();
    const coarsePointerQuery = window.matchMedia('(pointer: coarse)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const onMediaChange = () => syncInteractionModes();
    coarsePointerQuery.addEventListener('change', onMediaChange);
    reducedMotionQuery.addEventListener('change', onMediaChange);
    removeMediaListeners = () => {
        coarsePointerQuery.removeEventListener('change', onMediaChange);
        reducedMotionQuery.removeEventListener('change', onMediaChange);
    };

    gsapCtx = gsap.context(() => {
        const motionFactor = prefersReducedMotion.value ? 0.55 : 1;
        const tl = gsap.timeline({
            paused: true,
            defaults: { ease: 'expo.out', duration: 1.4 * motionFactor }
        });

        // Initial State
        gsap.set(bootOverlay.value, { autoAlpha: 1 });
        gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
        gsap.set('.online-pill', { autoAlpha: 0, y: 16, scale: 0.86 });
        gsap.set(terminalReveal.value, {
            autoAlpha: 0,
            y: 48,
            scale: 0.97,
            filter: prefersReducedMotion.value ? 'none' : 'blur(16px)',
        });

        // Entrance Animation
        tl.to(bootText.value, {
            opacity: 1,
            y: 0,
            stagger: 0.04,
            duration: 0.8 * motionFactor,
            ease: 'back.out(2)'
        })
        .to('.boot-progress', {
            scaleX: 1,
            duration: 0.4 * motionFactor,
            ease: 'power4.inOut'
        }, '-=0.4')
        .to(bootOverlay.value, {
            clipPath: 'inset(0 0 100% 0)',
            duration: 1.4 * motionFactor,
            ease: 'power4.inOut',
            onComplete: () => {
                showBootOverlay.value = false;
                isBooted.value = true;
                gsap.to(terminalReveal.value, {
                    autoAlpha: 1,
                    y: 0,
                    scale: 1,
                    filter: 'blur(0px)',
                    duration: prefersReducedMotion.value ? 0.35 : 1.15,
                    delay: prefersReducedMotion.value ? 0 : 0.15,
                    ease: 'expo.out',
                    clearProps: 'filter',
                    onStart: () => {
                        isTerminalActive.value = true;
                    },
                });
                if (isLoggingOut.value) {
                    sessionStorage.removeItem('logged_out');
                    isLoggingOut.value = false;
                }
            }
        }, '+=0.2')
        .to(structuralLines.value, {
            scaleX: 1,
            scaleY: 1,
            stagger: 0.1,
            duration: 1.2,
            ease: 'power4.inOut'
        }, '-=0.8')
        .from('.nav-item', {
            y: -20,
            opacity: 0,
            stagger: 0.05,
            duration: 0.4 * motionFactor
        }, '-=0.8');

        // Premium Scroll Parallax for Background Grid
        gsap.to(backgroundGrid.value, {
            y: -200,
            ease: "none",
            scrollTrigger: {
                trigger: mainContainer.value,
                start: "top top",
                end: "bottom bottom",
                scrub: true
            }
        });

        // Global Ambient Animations
        if (ambientOrbs.value.length > 0) {
            gsap.to(ambientOrbs.value, {
                y: (index) => (index % 2 ? 18 : -18),
                x: (index) => (index % 2 ? -8 : 12),
                duration: 6,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                stagger: 0.5
            });
        }

        requestAnimationFrame(() => tl.play(0));
    }, mainContainer);

    nextTick(() => {
        initScrambleElements();
        initScrollReveal();
        initDirectionalReveal();
        
        // Signal Jitter for LivePulse (if we want to keep some logic here, but better in component)
        // We'll let components handle their own local animations for better performance
    });
});

onBeforeUnmount(() => {
    removeMediaListeners();
    if (gsapCtx) {
        gsapCtx.revert();
        gsapCtx = null;
    }
});

const orbLayers = [
    { style: 'width: 11rem; height: 11rem; left: -4.5rem; top: 5rem;' },
    { style: 'width: 14rem; height: 14rem; right: -5.5rem; top: 28%;' },
    { style: 'width: 9rem; height: 9rem; right: 18%; bottom: -4.5rem;' },
];
</script>

<template>
    <Head title="Welcome | LUAV Learning Engine" />
    
    <div 
        ref="mainContainer"
        @mousemove="handleGlobalMouseMove"
        class="theme-neutral-page relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20 transition-colors duration-500"
        :style="{ '--school-accent': brandAccentColor }"
    >
        <!-- Global Background Elements -->
        <div ref="mouseGlow" class="pointer-events-none fixed -left-[200px] -top-[200px] z-0 hidden h-[400px] w-[400px] rounded-full bg-primary/[0.06] blur-[150px] will-change-transform dark:bg-primary/[0.12] md:block"></div>
        <div ref="backgroundGrid" class="fixed inset-[-100px] z-0 pointer-events-none opacity-[0.025] dark:opacity-[0.05] will-change-transform">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>
        <div v-for="(orb, index) in orbLayers" :key="index" ref="ambientOrbs" class="ambient-orb pointer-events-none absolute z-[1] rounded-full bg-primary/5 blur-3xl" :style="orb.style"></div>

        <FloatingPrisms :prefers-reduced-motion="prefersReducedMotion" />

        <!-- Structural Lines -->
        <div class="fixed inset-y-0 left-4 lg:left-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed inset-y-0 right-4 lg:right-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-bottom" ref="structuralLines"></div>
        <div class="fixed inset-x-0 top-1/4 h-px bg-border/20 z-0 hidden lg:block origin-left" ref="structuralLines"></div>
        <div class="fixed inset-x-0 bottom-1/4 h-px bg-border/20 z-0 hidden lg:block origin-right" ref="structuralLines"></div>

        <WelcomeHeader 
            :can-register="canRegister"
            :auth="$page.props.auth"
            :dashboard="dashboard"
            :login="login"
            :register="register"
            :is-booted="isBooted"
            :branding="schoolBranding"
            @magnetic="handleMagnetic"
            @reset-magnetic="resetMagnetic"
        />

        <main class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28">
            <WelcomeHero 
                :can-register="canRegister"
                :auth="$page.props.auth"
                :dashboard="dashboard"
                :login="login"
                :register="register"
                :is-booted="isBooted"
                :branding="schoolBranding"
                @magnetic="handleMagnetic"
                @reset-magnetic="resetMagnetic"
                @watch-demo="openDemoVideo"
            >
                <template #background>
                    <NeuralParticleNetwork 
                        :is-coarse-pointer="isCoarsePointer" 
                        :prefers-reduced-motion="prefersReducedMotion"
                        :paused="isTransitioningTheme"
                    />
                </template>
            </WelcomeHero>

            <div
                id="engine"
                ref="terminalReveal"
                class="scroll-mt-32"
            >
                <SystemTerminal :active="isTerminalActive" />
            </div>

            <Motion
                :initial="{ opacity: 0, y: 30, filter: 'blur(10px)' }"
                :animate="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view-options="{ once: true, margin: '-100px' }"
                :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.2 }"
                class="mt-10 lg:mt-16 grid gap-4 lg:grid-cols-[1.4fr_1fr]"
            >
                <LivePulse />
                <EnvironmentPanel />
            </Motion>

            <Motion
                id="metrics"
                class="scroll-mt-32"
                :initial="{ opacity: 0 }"
                :animate="isBooted ? { opacity: 1 } : {}"
                :in-view="isBooted ? { opacity: 1 } : {}"
                :in-view-options="{ once: true }"
                :transition="{ duration: 1.5 }"
            >
                <SystemMetrics 
                    :total-users="totalUsers"
                    :total-exams="totalExams"
                    :total-assignments="totalAssignments"
                    :total-submissions="totalSubmissions"
                />
            </Motion>

            <Motion
                id="architecture"
                class="scroll-mt-32"
                :initial="{ opacity: 0, scale: 0.95, filter: 'blur(14px)' }"
                :animate="isBooted ? { opacity: 1, scale: 1, filter: 'blur(0px)' } : {}"
                :in-view="isBooted ? { opacity: 1, scale: 1, filter: 'blur(0px)' } : {}"
                :in-view-options="{ once: true, margin: '-50px' }"
                :transition="{ duration: 1.2, ease: [0.16, 1, 0.3, 1] }"
            >
                <ArchitectureStack />
            </Motion>

            <Motion
                :initial="{ opacity: 0, y: 50, filter: 'blur(16px)' }"
                :animate="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view-options="{ once: true }"
                :transition="{ duration: 1, ease: 'ease-out' }"
            >
                <DimensionalCore />
            </Motion>

            <FeatureCards 
                id="features"
                class="scroll-mt-32"
                :is-coarse-pointer="isCoarsePointer"
                :prefers-reduced-motion="prefersReducedMotion"
                :auth="$page.props.auth"
                :dashboard="dashboard"
                :login="login"
            />

            <AudiencePathways />

            <TrustReadiness />

            <AppPreviewShowcase />

            <ReportSuite />

            <Motion
                :initial="{ opacity: 0, y: 40, filter: 'blur(10px)' }"
                :animate="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view="isBooted ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}"
                :in-view-options="{ once: true }"
                :transition="{ duration: 0.8, ease: 'ease-out' }"
            >
                <SeasonCountdown :active-season="activeSeason" />
            </Motion>

            <Motion
                :initial="{ opacity: 0, x: -20, filter: 'blur(8px)' }"
                :animate="isBooted ? { opacity: 1, x: 0, filter: 'blur(0px)' } : {}"
                :in-view="isBooted ? { opacity: 1, x: 0, filter: 'blur(0px)' } : {}"
                :in-view-options="{ once: true }"
                :transition="{ duration: 0.8, delay: 0.1 }"
            >
                <DemoQuiz 
                    :auth="$page.props.auth"
                    :register="register"
                    :dashboard="dashboard"
                />
            </Motion>

            <Motion
                :initial="{ opacity: 0 }"
                :animate="isBooted ? { opacity: 1 } : {}"
                :in-view="isBooted ? { opacity: 1 } : {}"
                :in-view-options="{ once: true }"
                :transition="{ duration: 2 }"
            >
                <TechStackCarousel :is-coarse-pointer="isCoarsePointer" />
            </Motion>
        </main>

        <WelcomeFooter />

        <!-- Entrance Boot Overlay -->
        <div 
            v-if="showBootOverlay"
            ref="bootOverlay"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-background p-6"
        >
            <div class="scanline absolute inset-0 pointer-events-none opacity-[0.05]"></div>
            <div class="relative flex flex-col items-center gap-4">
                <div class="flex flex-col items-center gap-2">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span v-for="(letter, i) in bootMessage.split('')" :key="i" ref="bootText" class="text-xs font-black tracking-[0.6em] uppercase opacity-0 translate-y-4">
                            {{ letter === ' ' ? '\u00A0' : letter }}
                        </span>
                    </div>
                    <div class="h-px w-32 bg-primary/20 overflow-hidden">
                        <div class="boot-progress h-full w-full bg-primary origin-left scale-x-0"></div>
                    </div>
                    <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest opacity-60 mt-1">{{ bootSubtext }}</span>
                </div>
            </div>
        </div>

        <DemoVideoModal :open="isDemoVideoOpen" :video-url="demoVideoUrl" @close="closeDemoVideo" />
    </div>
</template>

<style>
/* Core necessary styles that are global or used by GSAP */
.preserve-3d { transform-style: preserve-3d; }
.scanline {
    background: linear-gradient(to bottom, transparent 50%, rgba(0, 0, 0, 0.5) 51%);
    background-size: 100% 4px;
}
@keyframes scan-vertical {
    from { transform: translateY(0); }
    to { transform: translateY(100vh); }
}
@keyframes scan-horizontal {
    from { transform: translateX(-100%); }
    to { transform: translateX(1000%); }
}
/* Anti-FOUC for scroll-reveal targets — GSAP takes over on mount. */
[data-reveal] { opacity: 0; will-change: transform, opacity, filter; }
@media (prefers-reduced-motion: reduce) {
    [data-reveal] { opacity: 1 !important; transform: none !important; filter: none !important; }
}
</style>
