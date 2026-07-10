<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Only the essential sub-components
import DemoVideoModal from '@/components/welcome/DemoVideoModal.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import NeuralParticleNetwork from '@/components/welcome/NeuralParticleNetwork.vue';
import TechStackCarousel from '@/components/welcome/TechStackCarousel.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';

// Composables & Routes
import { useAppearance } from '@/composables/useAppearance';
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
const isCoarsePointer = ref(false);
const prefersReducedMotion = ref(false);

const { isTransitioningTheme } = useAppearance();

const brandAccentColor = computed(
    () => props.schoolBranding?.accentColor || '#f59e0b',
);

const openDemoVideo = () => {
    isDemoVideoOpen.value = true;
};

const closeDemoVideo = () => {
    isDemoVideoOpen.value = false;
};
</script>

<template>
    <Head title="Welcome | LUAV Learning Engine" />

    <div
        class="welcome-root relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground transition-colors duration-500 selection:bg-primary/20"
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
            :dashboard="dashboard"
            :login="login"
            :register="register"
            :is-booted="isBooted"
            :branding="schoolBranding"
        />

        <main
            class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28"
        >
            <WelcomeHero
                :can-register="canRegister"
                :auth="$page.props.auth"
                :dashboard="dashboard"
                :login="login"
                :register="register"
                :is-booted="isBooted"
                :branding="schoolBranding"
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

            <FeatureCards
                id="features"
                class="scroll-mt-32 mt-24"
                :is-coarse-pointer="isCoarsePointer"
                :prefers-reduced-motion="prefersReducedMotion"
                :auth="$page.props.auth"
                :dashboard="dashboard"
                :login="login"
            />

            <!-- How It Works -->
            <section
                id="architecture"
                class="scroll-mt-32 mt-32"
            >
                <div class="flex flex-col gap-2 mb-10">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 self-start">
                        <span class="text-sm font-medium text-primary">How It Works</span>
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        From enrollment to
                        <span class="text-primary">achievement</span>
                    </h2>
                    <p class="max-w-xl text-muted-foreground">
                        Five steps from sign-up to success — no fluff, no distractions.
                    </p>
                </div>

                <!-- Horizontal step cards: scrollable on mobile, grid on desktop -->
                <div
                    class="-mx-6 flex gap-4 overflow-x-auto px-6 pb-4 snap-x snap-mandatory scrollbar-none lg:mx-0 lg:grid lg:grid-cols-5 lg:gap-px lg:overflow-visible lg:rounded-xl lg:border lg:border-border/10 lg:bg-border/10 lg:p-0 lg:snap-none"
                >
                    <div
                        v-for="(step, i) in [
                            { title: 'Enroll', description: 'Join your section and access your courses, exams, and assignments in one place.' },
                            { title: 'Take Exams', description: 'Complete assessments in your browser with instant AI feedback on every answer.' },
                            { title: 'Get Feedback', description: 'Know where you stand immediately — auto-graded questions and AI-powered essay reviews.' },
                            { title: 'Track Progress', description: 'Monitor XP, streaks, and grades across all subjects on your dashboard.' },
                            { title: 'Earn Rewards', description: 'Unlock badges, seasonal achievements, and new nodes on your learning map.' },
                        ]"
                        :key="step.title"
                        class="flex min-w-[260px] shrink-0 snap-start flex-col gap-3 rounded-xl border border-border/15 bg-background p-5 lg:min-w-0 lg:flex-1 lg:rounded-none lg:border-0 lg:border-r lg:border-border/10 lg:p-6 lg:last:border-r-0"
                    >
                        <span class="text-[11px] font-semibold tracking-widest text-muted-foreground/50 uppercase">
                            Step {{ String(i + 1).padStart(2, '0') }}
                        </span>
                        <h3 class="text-sm font-semibold lg:text-base">
                            {{ step.title }}
                        </h3>
                        <p class="text-xs leading-relaxed text-muted-foreground lg:text-sm">
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

/* Force Inter on the welcome page regardless of dashboard font presets.
   Uses higher specificity than :root[data-font-preset] .font-sans (0-3-1 vs 0-3-0).
   The * selector ensures child elements with font-sans are also overridden. */
html[data-font-preset] .welcome-root.font-sans,
html[data-font-preset] .welcome-root.font-sans * {
    font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
}
</style>
