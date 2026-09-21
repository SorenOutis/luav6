<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    BookOpen,
    CheckCircle2,
    PenLine,
    Trophy,
} from 'lucide-vue-next';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import { about, dashboard, login, register } from '@/routes';

const canRegister = true;

const steps = [
    {
        number: '01',
        icon: BookOpen,
        title: 'Enroll in Your Section',
        phase: 'Classroom Onboarding',
        description:
            'Join your class by selecting your section. Teachers assign you to courses, exams, and assignments automatically with zero manual setup.',
        details: [
            'Secure section enrollment with teacher-controlled codes',
            'All your course materials consolidated into one hub',
            'Upcoming activities and exams visible on your daily timeline',
        ],
    },
    {
        number: '02',
        icon: PenLine,
        title: 'Complete Formative Work',
        phase: 'Active Learning',
        description:
            'Solve quizzes, exams, and short-answer prompts directly in your browser with clean, distraction-free timers and auto-save.',
        details: [
            'Multiple-choice, identification, and open-ended essay prompts',
            'Immediate objective scoring with zero waiting time',
            'Transparent rubric traces so expectations stay clear',
        ],
    },
    {
        number: '03',
        icon: CheckCircle2,
        title: 'Receive Guided Feedback',
        phase: 'Feedback Loop',
        description:
            'Get actionable guidance while the material is still fresh in your mind. AI assists by drafting comments, which your teacher reviews and approves.',
        details: [
            'Instant diagnostics on objective questions',
            'Teacher-verified AI feedback on written responses',
            'Clear next-step explanations pointing to key concepts to review',
        ],
    },
    {
        number: '04',
        icon: BarChart3,
        title: 'Build Consistent Habits',
        phase: 'Momentum & Habit',
        description:
            'Track your growth across every subject. Daily practice streaks and friendly section leaderboards reward steady effort over cramming.',
        details: [
            'Interactive dashboard tracking XP, level, and daily streaks',
            'Visual progress history across assignments and quizzes',
            'Section-scoped podiums celebrating classroom growth',
        ],
    },
    {
        number: '05',
        icon: Trophy,
        title: 'Master the Next Lesson',
        phase: 'Curriculum Pacing',
        description:
            'Because assessments pinpoint exact learning gaps, your teacher knows what to reteach tomorrow, keeping your entire class moving forward together.',
        details: [
            'Automated class mastery insights for targeted reteaching',
            'DepEd-aligned formative milestones and review packets',
            'Quarterly grade summaries with complete feedback history',
        ],
    },
];

const seoJsonLd = [
    {
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: 'How LSI Works — From enrollment to achievement',
        description:
            'Five clear steps: enroll, take exams, get instant feedback, track progress, and celebrate milestones with LSI.',
        author: {
            '@type': 'Organization',
            '@id': 'https://lsi.koamishin.com/#organization',
        },
        publisher: { '@id': 'https://lsi.koamishin.com/#organization' },
        mainEntityOfPage: 'https://lsi.koamishin.com/how-it-works',
    },
    {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Home',
                item: 'https://lsi.koamishin.com/',
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'How It Works',
                item: 'https://lsi.koamishin.com/how-it-works',
            },
        ],
    },
];
</script>

<template>
    <Head title="How It Works — LSI | From enrollment to achievement" />
    <SeoHead
        title="How It Works — LSI | From enrollment to achievement"
        description="Five clear steps: enroll, take exams, get instant feedback, track progress, and celebrate milestones with LSI."
        type="article"
        :jsonld="seoJsonLd"
    />

    <div
        class="howitworks-root relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <!-- Subtle ambient warmth bloom -->
        <div
            class="pointer-events-none absolute top-24 left-1/2 -z-10 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-[#D97757]/[0.05] blur-[120px]"
            aria-hidden="true"
        ></div>

        <WelcomeHeader
            :can-register="canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :is-booted="true"
        />

        <main
            class="relative z-10 mx-auto max-w-[960px] px-5 pt-16 pb-24 sm:px-8 sm:pt-20 sm:pb-32 lg:pt-28"
        >
            <!-- Hero -->
            <section class="text-center">
                <p
                    class="mb-4 text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                >
                    The Learning Journey
                </p>
                <h1
                    class="font-serif text-4xl leading-[1.02] tracking-[-0.045em] text-foreground sm:text-5xl lg:text-6xl"
                >
                    From enrollment to
                    <span class="text-[#D97757]">achievement</span>.
                </h1>
                <p
                    class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    LSI connects assessment, feedback, and classroom follow-up
                    into one seamless daily workflow. Here is how it happens.
                </p>
            </section>

            <!-- Steps Timeline -->
            <section class="mt-20 space-y-12 sm:mt-24 sm:space-y-16">
                <article
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="surface-card relative rounded-2xl border border-border/80 bg-card p-6 shadow-md sm:p-8 md:grid md:grid-cols-[64px_1fr] md:gap-8 lg:p-10"
                >
                    <!-- Left Monospace Step Counter & Icon -->
                    <div class="hidden md:flex md:flex-col md:items-center">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#D97757]/20 bg-[#D97757]/10 text-[#D97757] shadow-xs"
                        >
                            <component
                                :is="step.icon"
                                class="h-6 w-6"
                                stroke-width="1.6"
                            />
                        </div>
                        <span
                            class="mt-4 font-mono text-xs font-bold text-[#D97757]"
                        >
                            {{ step.number }}
                        </span>
                        <div
                            v-if="i < steps.length - 1"
                            class="mt-3 h-full w-px bg-border/60"
                        ></div>
                    </div>

                    <!-- Content -->
                    <div class="flex flex-col gap-3">
                        <!-- Mobile Step Indicator -->
                        <div
                            class="flex items-center justify-between md:hidden"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#D97757]/20 bg-[#D97757]/10 text-[#D97757]"
                            >
                                <component
                                    :is="step.icon"
                                    class="h-5 w-5"
                                    stroke-width="1.6"
                                />
                            </div>
                            <span
                                class="font-mono text-xs font-bold text-[#D97757]"
                            >
                                STEP {{ step.number }}
                            </span>
                        </div>

                        <div>
                            <span
                                class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ step.phase }}
                            </span>
                            <h2
                                class="mt-1 font-serif text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ step.title }}
                            </h2>
                        </div>

                        <p
                            class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            {{ step.description }}
                        </p>

                        <ul
                            class="mt-3 space-y-2 border-t border-border/60 pt-4"
                        >
                            <li
                                v-for="detail in step.details"
                                :key="detail"
                                class="flex items-start gap-2.5 text-xs text-foreground/90 sm:text-sm"
                            >
                                <span
                                    class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#D97757]"
                                ></span>
                                <span>{{ detail }}</span>
                            </li>
                        </ul>
                    </div>
                </article>
            </section>

            <!-- Final CTA Banner -->
            <section
                id="contact"
                class="welcome-cta relative mt-24 overflow-hidden rounded-2xl bg-primary px-6 py-10 text-primary-foreground shadow-xl sm:mt-32 sm:px-10 sm:py-14"
            >
                <div
                    class="pointer-events-none absolute -top-20 -right-20 -z-0 h-72 w-72 rounded-full bg-[#D97757]/20 blur-3xl"
                    aria-hidden="true"
                ></div>

                <div
                    class="relative z-10 flex flex-col gap-6 text-center sm:flex-row sm:items-center sm:justify-between sm:text-left"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                        >
                            Ready to Begin
                        </p>
                        <h2
                            class="mt-2 font-serif text-2xl font-semibold tracking-tight text-primary-foreground sm:text-3xl"
                        >
                            Start with the next lesson.
                        </h2>
                        <p
                            class="mt-2 max-w-md text-xs text-primary-foreground/70 sm:text-sm"
                        >
                            Join your institution's learning journey. If you're
                            a student, ask your teacher for your section code.
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <Link
                            :href="register().url"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#D97757] px-6 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2"
                        >
                            Create a free account
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <Link
                            :href="about().url"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-primary-foreground/45 px-5 text-sm font-medium text-primary-foreground transition-colors hover:border-primary-foreground hover:bg-primary-foreground/10"
                        >
                            About LSI
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>
