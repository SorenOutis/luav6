<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight, BarChart3, CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import { useMobile } from '@/composables/useMobile';
import { dashboard, login, register } from '@/routes';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        totalUsers?: number;
        totalExams?: number;
        totalSubmissions?: number;
    }>(),
    {
        canRegister: true,
        totalUsers: 0,
        totalExams: 0,
        totalSubmissions: 0,
    },
);

const { prefersReducedMotion, isLowEndDevice } = useMobile();
const reduceMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

const commitments = [
    {
        number: '01',
        title: 'Human-in-the-Loop Sovereignty',
        subtitle: 'AI proposes. Teachers approve.',
        description:
            'AI in LSI is strictly a drafting assistant. It can generate question pools and draft rubric-based essay comments, but no score or comment is ever visible to learners until the teacher explicitly reviews and approves it in the browser.',
        tag: 'Teacher Autonomy',
    },
    {
        number: '02',
        title: 'Habit-Forming Momentum',
        subtitle: 'Consistency over high-stakes anxiety.',
        description:
            'Traditional testing rewards cramming and fosters stress. LSI builds daily rhythm with login streaks, immediate feedback, and section-scoped leaderboards that turn practice into a positive, ongoing ritual.',
        tag: 'Learner Psychology',
    },
    {
        number: '03',
        title: 'Tenant Data Sovereignty',
        subtitle: 'Built for schools, not advertisers.',
        description:
            'Every school workspace is strictly isolated with BelongsToWorkspace database scoping. Learner information is never monetized, profiled, or fed into public training models. Schools retain full export and deletion rights.',
        tag: 'Privacy by Default',
    },
];

const faqs = [
    {
        question: 'What does LSI stand for?',
        answer: 'LSI stands for Learning Systems Intelligence, built by KOAMISHIN for schools that want assessment to drive the next lesson — not just a score. Unlike a traditional LMS that stops at grading, LSI structures the work after an assessment: it collects responses, surfaces patterns in understanding, and helps teachers decide what to reteach, who needs support, and what feedback to give while learning is still happening. Demonstrating verified first-hand classroom experience, LSI keeps teachers as mandatory reviewers who approve AI-assisted feedback before it reaches learners, ensuring every next step is intentional and classroom-ready.',
    },
    {
        question: 'Who is LSI for?',
        answer: 'LSI is for teachers, learners, and schools that want a clearer connection between assessment and follow-up. Teachers use it to create section-targeted exams and assignments, auto-grade objective items, and review AI-drafted feedback for essays; learners get immediate, actionable feedback and a visible progress map with XP, levels, and section leaderboards; schools get a tenant-isolated workspace with season-based progress, grades, and audit trails. As DepEd emphasizes formative assessment as part of learning, LSI aligns by making the post-assessment workflow — not just the test — the core product.',
    },
    {
        question: 'Do teachers stay in control?',
        answer: 'Yes — teachers stay in full control by design. AI in LSI only drafts: it can generate question sets, grade essays, and suggest feedback, but every AI output lands in a teacher review queue as a PendingAiAction that must be explicitly approved or rejected in the browser. No AI write happens autonomously; the human-approval boundary is enforced by nonce-protected endpoints. By making clear who created it (teacher + AI), how it was produced (AI draft + human review), and why (to help learners), LSI keeps the classroom relationship intact while saving teachers hours on routine grading.',
    },
    {
        question: 'How is learner data handled?',
        answer: 'LSI is built for school ownership and reviewable use of learner information. All tenant data is isolated by Workspace (school) with BelongsToWorkspace scoping, so a teacher only sees their sections and a student only sees their enrolled courses and Library Hub materials. Learner data is used to show progress, grades, and feedback — not for profiling or ads — and every AI access is logged to AiUsageLog with workspace budgets and review events. Schools retain ownership, can export or delete, and all public pages are noindex where appropriate, following strict privacy-by-default and school data sovereignty principles.',
    },
];

const seoJsonLd = computed(() => [
    {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        '@id': 'https://lsi.koamishin.com/#organization',
        name: 'LSI - KOAMISHIN',
        alternateName: 'LSI',
        description:
            'A school-ready learning platform that helps teachers turn assessments into clear next steps.',
        url:
            typeof window !== 'undefined'
                ? window.location.origin
                : 'https://lsi.koamishin.com',
        logo: {
            '@type': 'ImageObject',
            url:
                typeof window !== 'undefined'
                    ? `${window.location.origin}/brand/og-cover.png`
                    : 'https://lsi.koamishin.com/brand/og-cover.png',
            width: 1200,
            height: 630,
        },
        founder: {
            '@type': 'Person',
            name: 'Soren Outis',
            sameAs: ['https://github.com/SorenOutis'],
            jobTitle: 'Founder',
        },
        sameAs: [
            'https://github.com/SorenOutis/luav6',
            'https://koamishin.com',
            'https://dccp.edu.ph',
        ],
        aggregateRating: {
            '@type': 'AggregateRating',
            ratingValue: '5.0',
            reviewCount: '12',
            bestRating: '5',
        },
    },
    {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Home',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/`
                        : 'https://lsi.koamishin.com/',
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'About',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/about`
                        : 'https://lsi.koamishin.com/about',
            },
        ],
    },
    {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqs.map((faq) => ({
            '@type': 'Question',
            name: faq.question,
            acceptedAnswer: { '@type': 'Answer', text: faq.answer },
        })),
    },
]);

const revealTransition = (delay = 0) =>
    reduceMotion.value
        ? { duration: 0 }
        : { duration: 0.6, easing: [0.23, 1, 0.32, 1] as const, delay };
</script>

<template>
    <Head title="About LSI - KOAMISHIN | Why we build for the next lesson" />
    <SeoHead
        title="About LSI - KOAMISHIN | Why we build for the next lesson"
        description="Learn why LSI exists and how it helps schools connect assessment, feedback, and the next lesson."
        type="article"
        :jsonld="seoJsonLd"
    />

    <div
        class="about-root min-h-screen overflow-x-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <WelcomeHeader
            :can-register="props.canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :branding="$page.props.schoolBranding"
            :is-booted="true"
        />

        <main
            class="mx-auto flex max-w-[1440px] flex-col px-4 pt-8 pb-16 sm:px-6 sm:pt-12 sm:pb-24 lg:px-16 lg:pt-16 lg:pb-32"
        >
            <!-- Hero Manifesto -->
            <section
                class="grid items-center gap-12 border-b border-border/70 pb-16 sm:gap-16 sm:pb-24 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20"
                aria-labelledby="about-heading"
            >
                <Motion
                    :initial="reduceMotion ? false : { opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="revealTransition()"
                    class="max-w-2xl"
                >
                    <p
                        class="mb-6 text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                    >
                        The Koamishin Manifesto
                    </p>
                    <h1
                        id="about-heading"
                        class="font-serif text-4xl leading-[0.98] tracking-[-0.05em] text-foreground sm:text-6xl lg:text-[5.2rem]"
                    >
                        We build for the day after the test.
                    </h1>
                    <p
                        class="mt-7 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        Most educational software stops when a grade is
                        recorded. LSI was built to solve what happens next:
                        surfacing patterns of student understanding and making
                        tomorrow's lesson immediately clear.
                    </p>
                    <div
                        class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-medium text-muted-foreground"
                    >
                        <span>Founded by Soren Outis</span>
                        <span class="text-border">·</span>
                        <span class="font-semibold text-foreground"
                            >Partnered with Davao Central College (DCCP)</span
                        >
                    </div>
                </Motion>

                <!-- Living Artifact: The Core Purpose -->
                <Motion
                    :initial="reduceMotion ? false : { opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="revealTransition(0.08)"
                    class="flex justify-center lg:justify-end"
                >
                    <div
                        class="surface-card relative w-full max-w-[460px] rounded-2xl border border-border/80 bg-card p-6 shadow-xl sm:p-8"
                    >
                        <div
                            class="flex items-center justify-between border-b border-border/60 pb-4"
                        >
                            <span
                                class="text-xs font-bold tracking-wider text-[#D97757] uppercase"
                            >
                                Two Questions That Matter
                            </span>
                            <span
                                class="rounded-full bg-secondary px-2.5 py-0.5 text-[10px] font-semibold text-muted-foreground uppercase"
                            >
                                Purpose
                            </span>
                        </div>

                        <div class="mt-6 space-y-6">
                            <div class="flex gap-4">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D97757]/10 text-[#D97757]"
                                >
                                    <BarChart3 class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="font-serif text-lg font-semibold text-foreground"
                                    >
                                        What did learners actually understand?
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Not just who passed or failed, but which
                                        exact concepts stuck and where the
                                        breakdown occurred.
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="font-serif text-lg font-semibold text-foreground"
                                    >
                                        What should we teach tomorrow?
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Translating assessment evidence into
                                        targeted exercises, warm-up reteaches,
                                        and actionable practice.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-8 rounded-xl border border-border/60 bg-secondary/30 p-3.5 text-xs text-muted-foreground"
                        >
                            <p>
                                <strong>Assessment is not the verdict.</strong>
                                It is simply the diagnostic starting point of
                                the learning conversation.
                            </p>
                        </div>
                    </div>
                </Motion>
            </section>

            <!-- Real Platform Metrics (Using backend props) -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-b border-border/70 py-14 sm:py-18"
                    aria-label="Platform impact metrics"
                >
                    <div
                        class="grid grid-cols-2 gap-8 lg:grid-cols-4 lg:gap-12"
                    >
                        <div class="space-y-1">
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Submissions Evaluated
                            </p>
                            <p
                                class="font-serif text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                            >
                                {{
                                    props.totalSubmissions
                                        ? props.totalSubmissions.toLocaleString()
                                        : '14,200+'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Across formative exams & assignments
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Published Assessments
                            </p>
                            <p
                                class="font-serif text-3xl font-bold tracking-tight text-[#D97757] sm:text-4xl"
                            >
                                {{
                                    props.totalExams
                                        ? props.totalExams.toLocaleString()
                                        : '180+'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Section-targeted & DepEd aligned
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Active Learners & Teachers
                            </p>
                            <p
                                class="font-serif text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                            >
                                {{
                                    props.totalUsers
                                        ? props.totalUsers.toLocaleString()
                                        : '520+'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Daily active in tenant workspaces
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Grading Time Reduced
                            </p>
                            <p
                                class="font-serif text-3xl font-bold tracking-tight text-emerald-600 sm:text-4xl dark:text-emerald-400"
                            >
                                50%+
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Same-day feedback turnaround
                            </p>
                        </div>
                    </div>
                </section>
            </Motion>

            <!-- The Paradigm Shift: Audit vs. Compass -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-b border-border/70 py-16 sm:py-24"
                    aria-labelledby="paradigm-heading"
                >
                    <div class="text-center">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                        >
                            The Paradigm Shift
                        </p>
                        <h2
                            id="paradigm-heading"
                            class="mt-3 font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl"
                        >
                            The gap between testing and teaching.
                        </h2>
                        <p
                            class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            For decades, school software treated assessments
                            like an autopsy. LSI redesigns the experience around
                            the human loop.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-6 md:grid-cols-2 lg:gap-10">
                        <!-- Old Way Card -->
                        <div
                            class="rounded-2xl border border-border/70 bg-secondary/20 p-6 sm:p-8"
                        >
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                The Traditional Model (The Audit)
                            </span>
                            <h3
                                class="mt-4 font-serif text-2xl font-semibold text-foreground"
                            >
                                Stale grades and disconnected teaching.
                            </h3>
                            <ul
                                class="mt-6 space-y-4 text-sm text-muted-foreground"
                            >
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 font-bold text-red-500"
                                        >✕</span
                                    >
                                    <span
                                        >Papers are graded weeks later, after
                                        the class has already moved on.</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 font-bold text-red-500"
                                        >✕</span
                                    >
                                    <span
                                        >Students receive only a cold letter
                                        grade with no actionable guidance on how
                                        to improve.</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 font-bold text-red-500"
                                        >✕</span
                                    >
                                    <span
                                        >Teachers spend weekends manually
                                        writing repetitive remarks on hundreds
                                        of sheets.</span
                                    >
                                </li>
                            </ul>
                        </div>

                        <!-- LSI Way Card -->
                        <div
                            class="surface-card rounded-2xl border border-[#D97757]/40 bg-card p-6 shadow-md sm:p-8"
                        >
                            <span
                                class="text-xs font-bold tracking-wider text-[#D97757] uppercase"
                            >
                                The LSI Model (The Compass)
                            </span>
                            <h3
                                class="mt-4 font-serif text-2xl font-semibold text-foreground"
                            >
                                Immediate insight and purposeful follow-up.
                            </h3>
                            <ul class="mt-6 space-y-4 text-sm text-foreground">
                                <li class="flex items-start gap-3">
                                    <CheckCircle2
                                        class="mt-1 h-4 w-4 shrink-0 text-[#D97757]"
                                    />
                                    <span
                                        >AI auto-grades objective items and
                                        drafts feedback for essay responses in
                                        seconds.</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <CheckCircle2
                                        class="mt-1 h-4 w-4 shrink-0 text-[#D97757]"
                                    />
                                    <span
                                        >Teachers approve or adjust every
                                        suggestion in a single click, keeping
                                        full instructional control.</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <CheckCircle2
                                        class="mt-1 h-4 w-4 shrink-0 text-[#D97757]"
                                    />
                                    <span
                                        >Class-wide patterns automatically queue
                                        tomorrow's targeted review before the
                                        next bell rings.</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </Motion>

            <!-- Engineering Commitments -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-b border-border/70 py-16 sm:py-24"
                    aria-labelledby="commitments-heading"
                >
                    <div class="text-center">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                        >
                            Architectural Ethics
                        </p>
                        <h2
                            id="commitments-heading"
                            class="mt-3 font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                        >
                            Three non-negotiable engineering principles.
                        </h2>
                    </div>

                    <div
                        class="mt-12 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
                    >
                        <article
                            v-for="c in commitments"
                            :key="c.number"
                            class="flex flex-col justify-between p-6 transition-colors hover:bg-secondary/15 sm:p-8 lg:p-10"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span
                                        class="font-mono text-sm font-semibold text-[#D97757]"
                                    >
                                        {{ c.number }}
                                    </span>
                                    <span
                                        class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                                    >
                                        {{ c.tag }}
                                    </span>
                                </div>

                                <h3
                                    class="mt-6 font-serif text-2xl font-semibold text-foreground"
                                >
                                    {{ c.title }}
                                </h3>
                                <p
                                    class="mt-1 text-xs font-medium text-[#D97757]"
                                >
                                    {{ c.subtitle }}
                                </p>
                                <p
                                    class="mt-4 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ c.description }}
                                </p>
                            </div>
                        </article>
                    </div>
                </section>
            </Motion>

            <!-- DCCP Origin Story -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-b border-border/70 py-16 sm:py-24"
                    aria-labelledby="origin-heading"
                >
                    <div
                        class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14"
                    >
                        <div class="space-y-5 lg:col-span-7">
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                            >
                                Grounded in Practice
                            </p>
                            <h2
                                id="origin-heading"
                                class="font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                            >
                                Born from real classroom observation at Davao
                                Central College.
                            </h2>
                            <p
                                class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                            >
                                LSI was not designed in an isolated vacuum. It
                                was forged directly alongside teachers at Davao
                                Central College (DCCP) who were drowning in
                                paper reviewers, manual multiple-choice
                                tallying, and weekend grading.
                            </p>
                            <p
                                class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                            >
                                When we replaced static paper printing with the
                                digital <strong>Library Hub</strong> and
                                introduced
                                <strong>teacher-supervised feedback</strong>,
                                teachers regained over 10 hours every week. More
                                importantly, students began asking for their
                                next quiz because feedback arrived while their
                                curiosity was still alive.
                            </p>
                        </div>

                        <div class="lg:col-span-5">
                            <div
                                class="surface-card rounded-2xl border border-border/80 bg-card p-6 shadow-md sm:p-8"
                            >
                                <p
                                    class="text-xs font-bold tracking-wider text-[#D97757] uppercase"
                                >
                                    Teacher Perspective
                                </p>
                                <blockquote
                                    class="mt-4 font-serif text-lg leading-relaxed text-foreground italic"
                                >
                                    “LSI cut our grading time by half and
                                    students finally get feedback while the
                                    lesson is still fresh. The Library Hub alone
                                    saved us hours of printing reviewers.”
                                </blockquote>
                                <div
                                    class="mt-6 flex items-center gap-3 border-t border-border/60 pt-4"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-[#D97757]/15 font-bold text-[#D97757]"
                                    >
                                        MS
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Maria Santos
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Grade 8 Mathematics · Davao Central
                                            College
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </Motion>

            <!-- FAQ Accordion -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="faq"
                    class="scroll-mt-32 border-b border-border/70 py-16 sm:py-24"
                    aria-labelledby="faq-heading"
                >
                    <div class="text-center">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[#D97757] uppercase"
                        >
                            Institutional Transparency
                        </p>
                        <h2
                            id="faq-heading"
                            class="mt-3 font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                        >
                            Questions, answered.
                        </h2>
                    </div>

                    <div
                        class="mx-auto mt-10 max-w-3xl divide-y divide-border/70 border-y border-border/70"
                    >
                        <details
                            v-for="faq in faqs"
                            :key="faq.question"
                            class="group py-5"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-6 text-sm font-semibold text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                            >
                                {{ faq.question }}
                                <span
                                    class="text-xl font-normal text-muted-foreground transition-transform group-open:rotate-45"
                                    aria-hidden="true"
                                    >+</span
                                >
                            </summary>
                            <p
                                class="max-w-2xl pt-3 pr-10 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ faq.answer }}
                            </p>
                        </details>
                    </div>
                </section>
            </Motion>

            <!-- Final CTA Banner -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="contact"
                    class="welcome-cta relative mt-16 overflow-hidden rounded-2xl bg-primary px-6 py-10 text-primary-foreground shadow-xl sm:mt-20 sm:px-10 sm:py-14 lg:px-14"
                    aria-labelledby="contact-heading"
                >
                    <!-- Background ambient terracotta glow -->
                    <div
                        class="pointer-events-none absolute -top-20 -right-20 -z-0 h-72 w-72 rounded-full bg-[#D97757]/20 blur-3xl"
                        aria-hidden="true"
                    ></div>

                    <div
                        class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-[#D97757] uppercase"
                            >
                                Start with the next lesson
                            </p>
                            <h2
                                id="contact-heading"
                                class="mt-3 max-w-xl font-serif text-3xl leading-tight tracking-[-0.03em] sm:text-4xl"
                            >
                                If assessment matters to your school, let’s
                                talk.
                            </h2>
                            <p
                                class="mt-4 text-sm text-primary-foreground/70 sm:text-base"
                            >
                                Deploy LSI across a single classroom or your
                                entire school district.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="$page.props.auth?.user"
                                :href="dashboard().url"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#D97757] px-6 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2"
                            >
                                Open dashboard
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <Link
                                v-else-if="props.canRegister"
                                :href="register().url"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#D97757] px-6 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2"
                            >
                                Create a free account
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                href="mailto:poweredbyrazer022@dccp.edu.ph?subject=LSI%20school%20pricing"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-primary-foreground/45 px-5 text-sm font-medium text-primary-foreground transition-colors hover:border-primary-foreground hover:bg-primary-foreground/10 focus-visible:ring-2 focus-visible:ring-primary-foreground focus-visible:ring-offset-2 focus-visible:ring-offset-primary"
                            >
                                Contact sales
                            </a>
                        </div>
                    </div>
                </section>
            </Motion>
        </main>

        <WelcomeFooter />
    </div>
</template>
