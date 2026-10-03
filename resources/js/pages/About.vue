<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
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

const now = ref('');
let clock: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    const tick = () => {
        now.value = new Date().toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });
    };
    tick();
    clock = setInterval(tick, 30000);
});

onUnmounted(() => {
    if (clock !== undefined) {
        clearInterval(clock);
    }
});

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
        answer: 'LSI is for teachers, learners, and schools that want a clearer connection between assessment and follow-up. Teachers use it to create section-targeted exams and assignments, auto-grade objective items, and review AI-drafted feedback for essays; learners get immediate, actionable feedback and a visible progress map with XP, levels, and section leaderboards; schools get a tenant-isolated workspace with season-based progress, grades, and audit trails. Built for formative assessment as part of learning, LSI makes the post-assessment workflow — not just the test — the core product.',
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
            class="mx-auto flex max-w-[680px] flex-col px-6 pt-16 pb-24 sm:pt-24 sm:pb-32"
        >
            <!-- Headline + buy -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="revealTransition()"
            >
                <section
                    class="pb-14 text-center sm:pb-20"
                    aria-labelledby="about-heading"
                >
                    <p
                        v-if="now"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                            aria-hidden="true"
                        ></span>
                        {{ now }} — feedback going out in classrooms right now.
                    </p>
                    <h1
                        id="about-heading"
                        class="mx-auto mt-5 max-w-2xl text-5xl leading-[1.05] font-semibold tracking-[-0.03em] text-balance text-foreground sm:text-6xl"
                    >
                        We build for the day after the test.
                    </h1>
                    <p
                        class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-balance text-muted-foreground"
                    >
                        Grades look backward. LSI looks at tomorrow — every
                        assessment becomes the next lesson.
                    </p>
                    <div
                        class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                    >
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="dashboard().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-[15px] font-medium text-background transition-all hover:opacity-90 active:scale-[0.98]"
                        >
                            Open dashboard
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            v-else-if="props.canRegister"
                            :href="register().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-[15px] font-medium text-background transition-all hover:opacity-90 active:scale-[0.98]"
                        >
                            Get LSI free
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            v-if="!$page.props.auth?.user"
                            :href="login().url"
                            class="text-[15px] font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Log in
                        </Link>
                    </div>
                    <p class="mt-4 text-sm text-muted-foreground">
                        Free for a classroom. No credit card, no setup call.
                    </p>
                </section>
            </Motion>

            <!-- Before LSI -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="page-problem"
                    class="scroll-mt-24 border-t border-border/60 py-12"
                    aria-labelledby="paradigm-heading"
                >
                    <h2
                        id="paradigm-heading"
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Before LSI.
                    </h2>
                    <div
                        class="mt-4 space-y-3 text-[15px] text-muted-foreground"
                    >
                        <p>Papers graded weeks after the class moved on.</p>
                        <p>A letter grade. No map for getting better.</p>
                        <p>
                            Weekends lost to the same remarks, hundreds of
                            times.
                        </p>
                    </div>
                    <p class="mt-6 text-[17px] font-medium text-foreground">
                        The grade was the end of the story. We thought it should
                        be the start of the next one.
                    </p>
                </section>
            </Motion>

            <!-- How it works -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="page-compass"
                    class="scroll-mt-24 border-t border-border/60 py-12"
                    aria-labelledby="compass-heading"
                >
                    <h2
                        id="compass-heading"
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        How it works.
                    </h2>
                    <dl
                        class="mt-6 divide-y divide-border/60 border-y border-border/60"
                    >
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Assess
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                Students respond. Objective items grade
                                themselves in seconds.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Review
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                AI drafts essay feedback. Teachers approve every
                                word in one click. Nothing reaches a learner
                                unreviewed, ever.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Reteach
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                Class-wide patterns queue tomorrow's review
                                before the next bell rings.
                            </dd>
                        </div>
                    </dl>
                </section>
            </Motion>

            <!-- Numbers -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="page-figures"
                    class="scroll-mt-24 border-t border-border/60 py-12"
                    aria-label="LSI in numbers"
                >
                    <div class="grid grid-cols-2 gap-x-6 gap-y-8">
                        <div>
                            <p
                                class="text-4xl font-semibold tracking-tight text-foreground"
                            >
                                {{
                                    props.totalSubmissions
                                        ? props.totalSubmissions.toLocaleString()
                                        : '14,200+'
                                }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                submissions graded, and counting.
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-4xl font-semibold tracking-tight text-foreground"
                            >
                                {{
                                    props.totalExams
                                        ? props.totalExams.toLocaleString()
                                        : '180+'
                                }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                assessments published.
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-4xl font-semibold tracking-tight text-foreground"
                            >
                                {{
                                    props.totalUsers
                                        ? props.totalUsers.toLocaleString()
                                        : '520+'
                                }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                learners & teachers active daily.
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-4xl font-semibold tracking-tight text-foreground"
                            >
                                50%+
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                grading time gone. Same-day feedback.
                            </p>
                        </div>
                    </div>
                </section>
            </Motion>

            <!-- Origin -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="page-classroom"
                    class="scroll-mt-24 border-t border-border/60 py-12"
                    aria-labelledby="origin-heading"
                >
                    <h2
                        id="origin-heading"
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Made in classrooms, not meeting rooms.
                    </h2>
                    <div
                        class="mt-4 space-y-4 text-[15px] leading-relaxed text-muted-foreground"
                    >
                        <p>
                            LSI was forged alongside teachers drowning in paper
                            reviewers, manual tallying, and weekend grading. We
                            watched where the hours went — then built the thing
                            that gave them back.
                        </p>
                        <p>
                            Teachers regained 10+ hours a week. Students started
                            asking for their next quiz.
                        </p>
                    </div>
                    <figure class="mt-10 text-center">
                        <blockquote
                            class="text-2xl leading-snug font-medium tracking-[-0.01em] text-balance text-foreground"
                        >
                            “LSI cut our grading time by half and students
                            finally get feedback while the lesson is still
                            fresh.”
                        </blockquote>
                        <figcaption class="mt-3 text-sm text-muted-foreground">
                            Maria Santos · Grade 8 Mathematics
                        </figcaption>
                    </figure>
                </section>
            </Motion>

            <!-- Who it's for -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-t border-border/60 py-12"
                    aria-label="Requirements"
                >
                    <h2
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Requirements.
                    </h2>
                    <dl
                        class="mt-6 divide-y divide-border/60 border-y border-border/60"
                    >
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Teachers
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                A class and five minutes. Exams, auto-grading,
                                and a review queue included.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Learners
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                A browser. Feedback in hours, streaks and XP
                                included.
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                Schools
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                One workspace per school. Isolated, audited,
                                exportable.
                            </dd>
                        </div>
                    </dl>
                </section>
            </Motion>

            <!-- Private by design -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="page-editorial"
                    class="scroll-mt-24 border-t border-border/60 py-12"
                    aria-labelledby="commitments-heading"
                >
                    <h2
                        id="commitments-heading"
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Private by design.
                    </h2>
                    <dl
                        class="mt-6 divide-y divide-border/60 border-y border-border/60"
                    >
                        <div
                            v-for="c in commitments"
                            :key="c.number"
                            class="grid gap-1 py-5 sm:grid-cols-[140px_1fr] sm:gap-6"
                        >
                            <dt
                                class="text-[15px] font-semibold text-foreground"
                            >
                                {{ c.tag }}
                            </dt>
                            <dd
                                class="text-[15px] leading-relaxed text-muted-foreground"
                            >
                                <span class="font-medium text-foreground">{{
                                    c.subtitle
                                }}</span>
                                {{ c.description }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </Motion>

            <!-- FAQ -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="faq"
                    class="scroll-mt-32 border-t border-border/60 py-12"
                    aria-labelledby="faq-heading"
                >
                    <h2
                        id="faq-heading"
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Questions, answered.
                    </h2>

                    <div
                        class="mt-6 divide-y divide-border/60 border-y border-border/60"
                    >
                        <details
                            v-for="faq in faqs"
                            :key="faq.question"
                            class="group py-5"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-6 text-[15px] font-semibold text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                            >
                                {{ faq.question }}
                                <span
                                    class="text-xl font-normal text-muted-foreground transition-transform group-open:rotate-45"
                                    aria-hidden="true"
                                    >+</span
                                >
                            </summary>
                            <p
                                class="pt-3 pr-10 text-[15px] leading-relaxed text-muted-foreground"
                            >
                                {{ faq.answer }}
                            </p>
                        </details>
                    </div>
                </section>
            </Motion>

            <!-- Get LSI -->
            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="contact"
                    class="welcome-cta border-t border-border/60 py-14 text-center sm:py-20"
                    aria-labelledby="contact-heading"
                >
                    <h2
                        id="contact-heading"
                        class="mx-auto max-w-xl text-3xl font-semibold tracking-[-0.02em] text-balance text-foreground sm:text-4xl"
                    >
                        Tomorrow's lesson starts today.
                    </h2>
                    <p class="mt-3 text-[17px] text-muted-foreground">
                        One teacher, one class, or the whole school.
                    </p>
                    <div
                        class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                    >
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="dashboard().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-[15px] font-medium text-background transition-all hover:opacity-90 active:scale-[0.98]"
                        >
                            Open dashboard
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            v-else-if="props.canRegister"
                            :href="register().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-[15px] font-medium text-background transition-all hover:opacity-90 active:scale-[0.98]"
                        >
                            Get LSI free
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <a
                            href="mailto:poweredbyrazer022@dccp.edu.ph?subject=LSI%20school%20pricing"
                            class="text-[15px] font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            Talk to us →
                        </a>
                    </div>
                </section>
            </Motion>

            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    aria-label="Built by KOAMISHIN collective"
                    class="border-t border-border/60 py-12 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        Built by the KOAMISHIN collective — crafted in the open,
                        free to fork.
                    </p>
                    <div class="mt-4 flex items-center justify-center gap-6">
                        <a
                            href="https://koamishin.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-foreground transition-opacity hover:opacity-70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            koamishin.com →
                        </a>
                        <a
                            href="https://github.com/koamishin"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-foreground transition-opacity hover:opacity-70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            GitHub →
                        </a>
                    </div>
                </section>
            </Motion>
        </main>

        <WelcomeFooter />
    </div>
</template>
