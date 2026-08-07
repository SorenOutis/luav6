<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ArrowRight } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';

gsap.registerPlugin(ScrollTrigger);

// Only the essential sub-components
import SeoHead from '@/components/Seo/SeoHead.vue';
import DemoVideoModal from '@/components/welcome/DemoVideoModal.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import NeuralParticleNetwork from '@/components/welcome/NeuralParticleNetwork.vue';
import PricingSection from '@/components/welcome/PricingSection.vue';
import TechStackCarousel from '@/components/welcome/TechStackCarousel.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';

// Composables & Routes
import { useAppearance } from '@/composables/useAppearance';
import { syncLenisWithGsap } from '@/composables/useLenis';
import { useMobile } from '@/composables/useMobile';
import { dashboard, login, register } from '@/routes';

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

const isBooted = ref(true);
const isDemoVideoOpen = ref(false);
const { isCoarsePointer, prefersReducedMotion, isLowEndDevice } = useMobile();

const { isTransitioningTheme } = useAppearance();

// Low-end devices disable heavy animation even if the user hasn't set
// prefers-reduced-motion. Treat both signals as one so every child component
// (hero, feature cards, marquee, pricing) skips its continuous work on
// coarse-pointer / low-memory / few-core devices.
const effectiveReducedMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

const brandAccentColor = computed(
    () => props.schoolBranding?.accentColor || '#f59e0b',
);

const webSiteJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'LSI Learning Engine',
    alternateName: 'Learning Systems Intelligence',
    description:
        props.schoolBranding?.tagline ||
        'School-ready online assessment, exams, and assignments',
};

// ─── Refs for GSAP targets ───
const pageRoot = ref<HTMLElement | null>(null);
const howItWorksSteps = ref<HTMLElement | null>(null);
let gsapCtx: gsap.Context | null = null;
let lenisCleanup: (() => void) | null = null;

// ─── Animated Counter Animation ───
const animatedStats = ref({
    users: 0,
    exams: 0,
    assignments: 0,
    submissions: 0,
});
const statsRef = ref<HTMLElement | null>(null);

const animateCounter = (
    obj: {
        users: number;
        exams: number;
        assignments: number;
        submissions: number;
    },
    target: {
        users: number;
        exams: number;
        assignments: number;
        submissions: number;
    },
    duration: number,
) => {
    const start = performance.now();
    const update = (now: number) => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // Ease out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        obj.users = Math.round(target.users * eased);
        obj.exams = Math.round(target.exams * eased);
        obj.assignments = Math.round(target.assignments * eased);
        obj.submissions = Math.round(target.submissions * eased);
        animatedStats.value = { ...obj };
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
};

const initPageAnimations = () => {
    if (!pageRoot.value) return;

    // On low-end devices, skip GSAP context entirely — no scroll triggers, no animations
    if (isLowEndDevice.value) return;

    gsapCtx = gsap.context(() => {
        // ─── Section Reveals ───
        const sections = pageRoot.value?.querySelectorAll('.reveal-section');
        if (sections?.length) {
            gsap.fromTo(
                sections,
                { y: 60, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 1.2,
                    stagger: 0.2,
                    ease: 'expo.out',
                    scrollTrigger: {
                        trigger: sections,
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }

        // ─── How It Works Step Cards ───
        const stepCards = howItWorksSteps.value?.querySelectorAll('.step-card');
        if (stepCards?.length) {
            gsap.fromTo(
                stepCards,
                { y: 50, opacity: 0, scale: 0.95 },
                {
                    y: 0,
                    opacity: 1,
                    scale: 1,
                    duration: 0.9,
                    stagger: 0.15,
                    ease: 'expo.out',
                    scrollTrigger: {
                        trigger: howItWorksSteps.value,
                        start: 'top 80%',
                        toggleActions: 'play none none none',
                    },
                },
            );

            // Animate step numbers
            const stepNums =
                howItWorksSteps.value?.querySelectorAll('.step-number');
            if (stepNums?.length) {
                gsap.fromTo(
                    stepNums,
                    { scale: 0, rotation: -180 },
                    {
                        scale: 1,
                        rotation: 0,
                        duration: 0.6,
                        stagger: 0.15,
                        ease: 'back.out(2)',
                        scrollTrigger: {
                            trigger: howItWorksSteps.value,
                            start: 'top 80%',
                            toggleActions: 'play none none none',
                        },
                    },
                );
            }
        }

        // ─── Stats Counter ───
        if (statsRef.value && (props.totalUsers || props.totalExams)) {
            ScrollTrigger.create({
                trigger: statsRef.value,
                start: 'top 85%',
                onEnter: () => {
                    animateCounter(
                        { users: 0, exams: 0, assignments: 0, submissions: 0 },
                        {
                            users: props.totalUsers,
                            exams: props.totalExams,
                            assignments: props.totalAssignments,
                            submissions: props.totalSubmissions,
                        },
                        2000,
                    );
                },
                once: true,
            });
        }
    }, pageRoot.value);
};

// On low-end, eagerly display final animated stats without scroll-triggered animation
const initStatsDirect = () => {
    if (!statsRef.value) return;
    animatedStats.value = {
        users: props.totalUsers,
        exams: props.totalExams,
        assignments: props.totalAssignments,
        submissions: props.totalSubmissions,
    };
};

const openDemoVideo = () => {
    isDemoVideoOpen.value = true;
};

const closeDemoVideo = () => {
    isDemoVideoOpen.value = false;
};

onMounted(() => {
    // Set data-low-end on <html> so CSS can disable heavy effects
    if (isLowEndDevice.value) {
        document.documentElement.setAttribute('data-low-end', '');
        // Show final stats directly, skip all GSAP/ScrollTrigger/lenis
        initStatsDirect();
    } else {
        initPageAnimations();
        lenisCleanup = syncLenisWithGsap(ScrollTrigger);
    }
});

onUnmounted(() => {
    // Clean up the data attribute when leaving the welcome page
    document.documentElement.removeAttribute('data-low-end');
    gsapCtx?.revert();
    lenisCleanup?.();
});
</script>

<template>
    <Head title="School-Ready Assessments & Online Exams" />
    <SeoHead
        :description="'A school-ready learning platform for exams, assignments, grades, and AI feedback — with a clear path for every learner.'"
        type="website"
        :jsonld="webSiteJsonLd"
    />

    <div
        ref="pageRoot"
        class="welcome-root relative min-h-screen w-full bg-background font-sans text-foreground transition-colors duration-500 selection:bg-primary/20"
        :style="{ '--school-accent': brandAccentColor }"
    >
        <!-- Subtle background grid -->
        <div
            class="pointer-events-none fixed inset-0 z-0 opacity-[0.025] dark:opacity-[0.05]"
        >
            <div
                class="absolute inset-0"
                style="
                    background-image:
                        linear-gradient(
                            var(--color-border) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            90deg,
                            var(--color-border) 1px,
                            transparent 1px
                        );
                    background-size: 60px 60px;
                "
            ></div>
        </div>

        <WelcomeHeader
            :can-register="canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :is-booted="isBooted"
            :branding="schoolBranding"
        />

        <main
            class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28"
        >
            <WelcomeHero
                :can-register="canRegister"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
                :register="() => register().url"
                :is-booted="isBooted"
                :is-coarse-pointer="isCoarsePointer"
                :prefers-reduced-motion="effectiveReducedMotion"
                :branding="schoolBranding"
                @watch-demo="openDemoVideo"
            >
                <template #background>
                    <NeuralParticleNetwork
                        v-if="!isLowEndDevice"
                        :is-coarse-pointer="isCoarsePointer"
                        :prefers-reduced-motion="prefersReducedMotion"
                        :paused="isTransitioningTheme"
                    />
                </template>
            </WelcomeHero>

            <FeatureCards
                ref="featureCardsSection"
                id="features"
                class="reveal-section mt-24 scroll-mt-32"
                :is-coarse-pointer="isCoarsePointer"
                :prefers-reduced-motion="effectiveReducedMotion"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
            />

            <!-- Stats Counter Bar -->
            <div
                ref="statsRef"
                class="reveal-section mt-24 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border/10 bg-border/10 lg:grid-cols-4"
            >
                <div
                    class="flex flex-col items-center justify-center gap-1.5 bg-background py-8 lg:py-10"
                >
                    <span
                        class="text-3xl font-black tracking-tight text-foreground tabular-nums lg:text-4xl"
                        >{{ animatedStats.users.toLocaleString() }}</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-[0.2em] text-muted-foreground/60 uppercase"
                        >Students</span
                    >
                </div>
                <div
                    class="flex flex-col items-center justify-center gap-1.5 bg-background py-8 lg:py-10"
                >
                    <span
                        class="text-3xl font-black tracking-tight text-foreground tabular-nums lg:text-4xl"
                        >{{ animatedStats.exams.toLocaleString() }}</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-[0.2em] text-muted-foreground/60 uppercase"
                        >Exams Created</span
                    >
                </div>
                <div
                    class="flex flex-col items-center justify-center gap-1.5 bg-background py-8 lg:py-10"
                >
                    <span
                        class="text-3xl font-black tracking-tight text-foreground tabular-nums lg:text-4xl"
                        >{{ animatedStats.assignments.toLocaleString() }}</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-[0.2em] text-muted-foreground/60 uppercase"
                        >Assignments</span
                    >
                </div>
                <div
                    class="flex flex-col items-center justify-center gap-1.5 bg-background py-8 lg:py-10"
                >
                    <span
                        class="text-3xl font-black tracking-tight text-foreground tabular-nums lg:text-4xl"
                        >{{ animatedStats.submissions.toLocaleString() }}</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-[0.2em] text-muted-foreground/60 uppercase"
                        >Submissions</span
                    >
                </div>
            </div>

            <!-- How It Works -->
            <section
                id="architecture"
                class="reveal-section mt-32 scroll-mt-32"
            >
                <div class="mb-10 flex flex-col gap-2">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >How It Works</span
                        >
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        From enrollment to
                        <span class="text-primary">achievement</span>
                    </h2>
                    <p class="max-w-xl text-muted-foreground">
                        Five steps from sign-up to success — no fluff, no
                        distractions.
                    </p>
                </div>

                <!-- Remotion walkthrough animation -->
                <div
                    class="mb-10 overflow-hidden rounded-2xl border border-border/20 bg-black shadow-2xl shadow-primary/5"
                >
                    <video
                        class="block aspect-video w-full"
                        src="/videos/how-it-works.mp4?v=2"
                        poster="/videos/how-it-works.png"
                        :autoplay="!effectiveReducedMotion"
                        :loop="!effectiveReducedMotion"
                        muted
                        playsinline
                        :preload="isLowEndDevice ? 'metadata' : 'auto'"
                        aria-label="How LSI works from enrollment to achievement"
                    ></video>
                </div>

                <!-- Horizontal step cards: scrollable on mobile, grid on desktop -->
                <div
                    v-if="false"
                    ref="howItWorksSteps"
                    class="-mx-6 flex snap-x snap-mandatory scrollbar-none gap-4 overflow-x-auto px-6 pb-4 lg:mx-0 lg:grid lg:snap-none lg:grid-cols-5 lg:gap-px lg:overflow-visible lg:rounded-xl lg:border lg:border-border/10 lg:bg-border/10 lg:p-0"
                >
                    <div
                        v-for="(step, i) in [
                            {
                                title: 'Enroll',
                                description:
                                    'Join your section and access your courses, exams, and assignments in one place.',
                            },
                            {
                                title: 'Take Exams',
                                description:
                                    'Complete assessments in your browser with instant AI feedback on every answer.',
                            },
                            {
                                title: 'Get Feedback',
                                description:
                                    'Know where you stand immediately — auto-graded questions and AI-powered essay reviews.',
                            },
                            {
                                title: 'Track Progress',
                                description:
                                    'Monitor XP, streaks, and grades across all subjects on your dashboard.',
                            },
                            {
                                title: 'Earn Rewards',
                                description:
                                    'Unlock badges, seasonal achievements, and new nodes on your learning map.',
                            },
                        ]"
                        :key="step.title"
                        class="step-card flex min-w-[260px] shrink-0 snap-start flex-col gap-3 rounded-xl border border-border/15 bg-background p-5 lg:min-w-0 lg:flex-1 lg:rounded-none lg:border-0 lg:border-r lg:border-border/10 lg:p-6 lg:last:border-r-0"
                    >
                        <span
                            class="step-number text-[11px] font-semibold tracking-widest text-muted-foreground/50 uppercase"
                        >
                            Step {{ String(i + 1).padStart(2, '0') }}
                        </span>
                        <h3 class="text-sm font-semibold lg:text-base">
                            {{ step.title }}
                        </h3>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground lg:text-sm"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </div>

                <div class="mt-10 text-center">
                    <Link
                        href="/how-it-works"
                        class="inline-flex items-center gap-2 rounded-lg border border-border/30 px-5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/60 hover:text-foreground"
                    >
                        View full guide
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </section>

            <div class="reveal-section">
                <TechStackCarousel
                    :is-coarse-pointer="isCoarsePointer"
                    :prefers-reduced-motion="effectiveReducedMotion"
                />
            </div>

            <PricingSection
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
                :register="() => register().url"
                :is-coarse-pointer="isCoarsePointer"
                :prefers-reduced-motion="effectiveReducedMotion"
            />
        </main>

        <WelcomeFooter />

        <DemoVideoModal
            :open="isDemoVideoOpen"
            :video-url="demoVideoUrl"
            @close="closeDemoVideo"
        />
    </div>
</template>

<style>
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ─── Low-end device optimisations ───
   Applied via the `data-low-end` attribute on <html> when the system
   detects low-end hardware (coarse pointer, low memory, few cores, slow connection).
   Disables heavy CSS effects that the GSAP runtime already skips for these devices. */
html[data-low-end] * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
}

html[data-low-end] .backdrop-blur-sm,
html[data-low-end] .backdrop-blur,
html[data-low-end] .backdrop-blur-md,
html[data-low-end] .backdrop-blur-lg,
html[data-low-end] .backdrop-blur-xl,
html[data-low-end] .backdrop-blur-2xl {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

html[data-low-end] .will-change-transform {
    will-change: auto !important;
}

html[data-low-end] [class*='animate-ping'],
html[data-low-end] [class*='animate-pulse'],
html[data-low-end] [class*='animate-bounce'] {
    animation: none !important;
}

/* Force Inter on the welcome page regardless of dashboard font presets.
   Uses higher specificity than :root[data-font-preset] .font-sans (0-3-1 vs 0-3-0).
   The * selector ensures child elements with font-sans are also overridden. */
html[data-font-preset] .welcome-root.font-sans,
html[data-font-preset] .welcome-root.font-sans * {
    font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
}
</style>
