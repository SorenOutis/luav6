<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    PenLine,
    CheckCircle2,
    BarChart3,
    Trophy,
    ArrowRight,
} from 'lucide-vue-next';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';

const canRegister = true;

const steps = [
    {
        icon: BookOpen,
        title: 'Enroll in Your Section',
        description:
            'Join your class by selecting your section. Teachers assign you to courses, exams, and assignments automatically.',
        details: [
            'Secure section enrollment with optional password',
            'Access all your courses in one place',
            'See upcoming deadlines at a glance',
        ],
    },
    {
        icon: PenLine,
        title: 'Take Exams & Submit Work',
        description:
            'Complete exams, quizzes, and assignments directly in your browser. Multiple question types with instant AI feedback.',
        details: [
            'Multiple-choice, identification, and essay questions',
            'AI-assisted grading with instant feedback',
            'Track time and progress during exams',
        ],
    },
    {
        icon: CheckCircle2,
        title: 'Get Instant Feedback',
        description:
            'Receive immediate results on auto-graded questions and AI-powered feedback on essays. Know where you stand right away.',
        details: [
            'Automatic grading for objective questions',
            'AI-generated feedback on essay responses',
            'Detailed score breakdowns per exam part',
        ],
    },
    {
        icon: BarChart3,
        title: 'Track Your Progress',
        description:
            'Monitor your grades and progress across all subjects. The learning map shows your journey at every step.',
        details: [
            'Real-time dashboard with progress and level tracking',
            'Daily engagement system that rewards consistent work',
            'Section leaderboards for healthy classroom competition',
            'Comprehensive grades page for all subjects',
        ],
    },
    {
        icon: Trophy,
        title: 'Celebrate Milestones',
        description:
            'Stay motivated with achievements, review milestones, and an engaging learning experience that makes progress clear and rewarding.',
        details: [
            'Recognition for milestones and achievements',
            'Learning campaigns and seasonal milestones',
            'A learning map with unlockable checkpoints',
            'Classroom connection through shared sections and engagement',
        ],
    },
];

const seoJsonLd = [
    {
        '@context': 'https://schema.org',
        '@type': 'HowTo',
        name: 'How LSI Works',
        description: 'From enrollment to achievement in five clear steps.',
        step: steps.map((s, i) => ({
            '@type': 'HowToStep',
            position: i + 1,
            name: s.title,
            text: s.description,
        })),
    },
];
</script>

<template>
    <Head title="How It Works" />
    <SeoHead
        :description="'Five clear steps: enroll, take exams, get instant feedback, track progress, and celebrate milestones with LSI.'"
        type="article"
        :jsonld="seoJsonLd"
    />

    <div
        class="howitworks-root relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <!-- Background grid (desktop only) -->
        <div
            class="welcome-bg-grid pointer-events-none fixed inset-0 z-0 hidden opacity-[0.025] md:block dark:opacity-[0.05]"
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
            :dashboard="() => '/dashboard'"
            :login="() => '/login'"
            :register="() => '/register'"
            :is-booted="true"
        />

        <main
            class="relative z-10 mx-auto max-w-[900px] px-6 pt-24 pb-32 lg:px-16 lg:pt-32"
        >
            <!-- Hero -->
            <section class="text-center">
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5"
                >
                    <span class="text-sm font-medium text-primary"
                        >How It Works</span
                    >
                </div>
                <h1
                    class="text-4xl leading-tight font-bold tracking-tight sm:text-5xl lg:text-6xl"
                >
                    From enrollment to<br />
                    <span class="text-primary">achievement</span>
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                    LSI makes learning measurable, feedback instant, and
                    progress visible. Here's how every step works.
                </p>
            </section>

            <!-- Steps -->
            <section class="mt-24 space-y-20">
                <div
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="relative grid grid-cols-1 items-start gap-8 md:grid-cols-[48px_1fr] md:gap-10"
                >
                    <!-- Numbered connector (desktop) -->
                    <div class="hidden md:flex md:flex-col md:items-center">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10"
                        >
                            <component
                                :is="step.icon"
                                class="h-6 w-6 text-primary"
                            />
                        </div>
                        <div
                            v-if="i < steps.length - 1"
                            class="mt-4 h-full w-px bg-border/30"
                        ></div>
                    </div>

                    <!-- Content -->
                    <div class="flex flex-col gap-4">
                        <!-- Mobile icon -->
                        <div class="flex items-center gap-3 md:hidden">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
                            >
                                <component
                                    :is="step.icon"
                                    class="h-5 w-5 text-primary"
                                />
                            </div>
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Step {{ i + 1 }}</span
                            >
                        </div>

                        <h2
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            {{ step.title }}
                        </h2>
                        <p
                            class="text-base leading-relaxed text-muted-foreground"
                        >
                            {{ step.description }}
                        </p>
                        <ul class="mt-2 flex flex-col gap-2">
                            <li
                                v-for="detail in step.details"
                                :key="detail"
                                class="flex items-start gap-3 text-sm text-muted-foreground"
                            >
                                <span
                                    class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary/60"
                                ></span>
                                {{ detail }}
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section
                class="mt-32 rounded-2xl border border-border/20 bg-gradient-to-br from-primary/[0.04] via-transparent to-transparent p-10 text-center lg:p-16"
            >
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                    Ready to get started?
                </h2>
                <p class="mx-auto mt-3 max-w-lg text-muted-foreground">
                    Join your institution's learning journey. If you're a
                    student, ask your teacher for your section code.
                </p>
                <div
                    class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-lg bg-foreground px-6 py-3 text-sm font-semibold text-background transition-colors hover:bg-primary"
                    >
                        Create Account
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link
                        href="/about"
                        class="inline-flex items-center gap-2 rounded-lg border border-border/30 px-6 py-3 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/60 hover:text-foreground"
                    >
                        Learn More
                    </Link>
                </div>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>

<style>
/* Force Inter on the how-it-works page regardless of dashboard font presets.
   Uses higher specificity than :root[data-font-preset] .font-sans (0-3-1 vs 0-3-0).
   The * selector ensures child elements with font-sans are also overridden. */
html[data-font-preset] .howitworks-root.font-sans,
html[data-font-preset] .howitworks-root.font-sans * {
    font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
}
</style>
