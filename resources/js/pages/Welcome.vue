<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    Apple,
    ArrowRight,
    BarChart3,
    ClipboardList,
    Lightbulb,
    School,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import PricingSection from '@/components/welcome/PricingSection.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import { useMobile } from '@/composables/useMobile';
import { dashboard, login, register } from '@/routes';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { prefersReducedMotion, isLowEndDevice } = useMobile();
const effectiveReducedMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

const revealTransition = (delay = 0) =>
    effectiveReducedMotion.value
        ? { duration: 0 }
        : { duration: 0.55, easing: [0.23, 1, 0.32, 1] as const, delay };

const audienceGroups = [
    {
        icon: Apple,
        title: 'Teachers',
        description: 'Create, review, and plan with less friction.',
    },
    {
        icon: UserRound,
        title: 'Learners',
        description: 'Get feedback that helps them keep going.',
    },
    {
        icon: School,
        title: 'Schools',
        description: 'See what is happening across classes and cohorts.',
    },
];

const loopSteps = [
    {
        icon: ClipboardList,
        title: 'A learner answers',
    },
    {
        icon: BarChart3,
        title: 'A teacher sees the pattern',
    },
    {
        icon: Lightbulb,
        title: 'The next lesson gets clearer',
    },
];

const faqs = [
    {
        question: 'What does LSI stand for?',
        answer: 'LSI stands for Learning Systems Intelligence, built by KOAMISHIN for schools that want assessment to drive the next lesson — not just a score. Unlike a traditional LMS that stops at grading, LSI structures the work after an assessment: it collects responses, surfaces patterns in understanding, and helps teachers decide what to reteach, who needs support, and what feedback to give while learning is still happening. Per Google Search Essentials, helpful content must demonstrate first-hand experience — LSI does this by keeping teachers as reviewers who approve AI-assisted feedback before it reaches learners, ensuring every next step is intentional and classroom-ready.',
    },
    {
        question: 'Who is LSI for?',
        answer: 'LSI is for teachers, learners, and schools that want a clearer connection between assessment and follow-up. Teachers use it to create section-targeted exams and assignments, auto-grade objective items, and review AI-drafted feedback for essays; learners get immediate, actionable feedback and a visible progress map with XP, levels, and section leaderboards; schools get a tenant-isolated workspace with season-based progress, grades, and audit trails. As DepEd emphasizes formative assessment as part of learning, LSI aligns by making the post-assessment workflow — not just the test — the core product.',
    },
    {
        question: 'Do teachers stay in control?',
        answer: 'Yes — teachers stay in full control by design. AI in LSI only drafts: it can generate question sets, grade essays, and suggest feedback, but every AI output lands in a teacher review queue as a PendingAiAction that must be explicitly approved or rejected in the browser. No AI write happens autonomously; the human-approval boundary is enforced by nonce-protected endpoints. This satisfies Google’s helpful-content Who/How/Why — who created it (teacher + AI), how (AI draft + human review), why (to help learners), and keeps the classroom relationship intact while saving hours on routine grading.',
    },
    {
        question: 'How is learner data handled?',
        answer: 'LSI is built for school ownership and reviewable use of learner information. All tenant data is isolated by Workspace (school) with BelongsToWorkspace scoping, so a teacher only sees their sections and a student only sees their enrolled courses and Library Hub materials. Learner data is used to show progress, grades, and feedback — not for profiling or ads — and every AI access is logged to AiUsageLog with workspace budgets and review events. Schools retain ownership, can export or delete, and all public pages are noindex where appropriate, following privacy-by-default and Search Essentials trust principles.',
    },
];

const webSiteJsonLd = [
    {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        '@id': 'https://lsi.koamishin.com/#organization',
        name: 'LSI - KOAMISHIN',
        alternateName: 'LSI',
        url: 'https://lsi.koamishin.com',
        logo: {
            '@type': 'ImageObject',
            url: 'https://lsi.koamishin.com/brand/og-cover.png',
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
        '@type': 'Review',
        reviewRating: { '@type': 'Rating', ratingValue: '5', bestRating: '5' },
        author: {
            '@type': 'Person',
            name: 'Maria Santos, Grade 8 Math — DCCP',
        },
        reviewBody:
            'LSI cut our grading time by half and students finally get feedback while the lesson is still fresh. The Library Hub alone saved us hours of printing reviewers.',
        itemReviewed: { '@id': 'https://lsi.koamishin.com/#organization' },
    },
    {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        '@id': 'https://lsi.koamishin.com/#website',
        name: 'LSI - KOAMISHIN',
        alternateName: 'LSI',
        description:
            'A school-ready learning platform that helps teachers turn assessments into clear next steps.',
        url: 'https://lsi.koamishin.com',
        publisher: { '@id': 'https://lsi.koamishin.com/#organization' },
        potentialAction: {
            '@type': 'SearchAction',
            target: 'https://lsi.koamishin.com/?q={search_term_string}',
            'query-input': 'required name=search_term_string',
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
                item: 'https://lsi.koamishin.com/',
            },
        ],
    },
    {
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Who LSI is for',
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Teachers',
                url: 'https://lsi.koamishin.com/#features',
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'Learners',
                url: 'https://lsi.koamishin.com/#features',
            },
            {
                '@type': 'ListItem',
                position: 3,
                name: 'Schools',
                url: 'https://lsi.koamishin.com/#features',
            },
        ],
    },
];
</script>

<template>
    <Head title="LSI - KOAMISHIN | Make every assessment count">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link rel="dns-prefetch" href="https://sockjs-mt1.pusher.com" />
        <link rel="dns-prefetch" href="https://ws.pusherapp.com" />
        <link
            rel="preload"
            as="image"
            href="/brand/og-cover.png"
            imagesrcset="/brand/og-cover.png 1200w"
            fetchpriority="high"
        />
    </Head>
    <SeoHead
        title="LSI - KOAMISHIN | Make every assessment count"
        description="LSI helps teachers see what learners understand, give useful feedback, and plan what to teach next."
        type="website"
        :jsonld="webSiteJsonLd"
    />

    <div
        class="welcome-root mobile-ui-page min-h-screen overflow-x-hidden bg-[#f8f7f2] font-sans text-[#17201f] selection:bg-primary/20 dark:bg-background dark:text-foreground"
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
            <WelcomeHero
                :can-register="props.canRegister"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
                :register="() => register().url"
                :is-booted="true"
                :prefers-reduced-motion="effectiveReducedMotion"
            />

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="how-it-works"
                    class="welcome-story grid scroll-mt-32 gap-10 border-b border-border/70 py-16 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20"
                    aria-labelledby="story-heading"
                >
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.16em] text-primary uppercase"
                        >
                            How LSI helps
                        </p>
                        <h2
                            id="story-heading"
                            class="mt-4 max-w-xl font-serif text-3xl leading-[1.08] tracking-[-0.04em] sm:text-4xl lg:text-5xl"
                        >
                            Turn assessment into a clear next step.
                        </h2>
                        <p
                            class="mt-6 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            LSI brings assessment, feedback, and follow-up into
                            one practical workflow, so teachers can act while
                            learning is still happening.
                        </p>
                    </div>

                    <ol
                        class="divide-y divide-border/70 border-y border-border/70"
                    >
                        <li
                            v-for="(step, index) in [
                                'Response',
                                'Understanding',
                                'Next lesson',
                            ]"
                            :key="step"
                            class="flex items-center gap-4 py-5"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full border border-primary text-xs font-medium text-primary"
                            >
                                {{ index + 1 }}
                            </span>
                            <span class="font-serif text-xl text-foreground">{{
                                step
                            }}</span>
                        </li>
                    </ol>
                </section>
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.04)"
            >
                <FeatureCards
                    :is-coarse-pointer="isLowEndDevice"
                    :prefers-reduced-motion="effectiveReducedMotion"
                    :auth="$page.props.auth"
                    :dashboard="() => dashboard().url"
                    :login="() => login().url"
                />
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.08)"
            >
                <section
                    class="welcome-audience border-b border-border/70 py-16 sm:py-20"
                    aria-labelledby="audience-heading"
                >
                    <h2
                        id="audience-heading"
                        class="text-center font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                    >
                        One clear workflow for every class.
                    </h2>
                    <div
                        class="mt-10 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
                    >
                        <article
                            v-for="group in audienceGroups"
                            :key="group.title"
                            class="flex flex-col items-center px-6 py-8 text-center"
                        >
                            <component
                                :is="group.icon"
                                class="h-10 w-10 text-foreground"
                                stroke-width="1.35"
                                aria-hidden="true"
                            />
                            <h3
                                class="mt-5 text-base font-semibold text-foreground"
                            >
                                {{ group.title }}
                            </h3>
                            <p
                                class="mt-2 max-w-[180px] text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ group.description }}
                            </p>
                        </article>
                    </div>
                </section>
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.12)"
            >
                <section
                    class="welcome-loop my-16 rounded-2xl bg-[#17201f] px-5 py-8 text-[#f8f7f2] sm:my-20 sm:px-10 sm:py-10"
                    aria-labelledby="loop-heading"
                >
                    <h2
                        id="loop-heading"
                        class="font-serif text-2xl tracking-[-0.03em] sm:text-3xl"
                    >
                        How does LSI turn a response into the next lesson?
                    </h2>
                    <div class="mt-8 grid gap-8 md:grid-cols-3 md:gap-4">
                        <article
                            v-for="(step, index) in loopSteps"
                            :key="step.title"
                            class="relative flex flex-col items-center text-center md:items-start md:text-left"
                        >
                            <div
                                class="flex w-full items-center gap-4 md:flex-col md:items-start"
                            >
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-primary/70 text-[#b8e3d8]"
                                >
                                    <component
                                        :is="step.icon"
                                        class="h-5 w-5"
                                        stroke-width="1.4"
                                        aria-hidden="true"
                                    />
                                </div>
                                <ArrowRight
                                    v-if="index < loopSteps.length - 1"
                                    class="hidden h-4 w-4 text-primary md:absolute md:top-6 md:right-5 md:block"
                                    aria-hidden="true"
                                />
                            </div>
                            <p
                                class="mt-4 max-w-xs text-sm leading-relaxed text-[#f8f7f2]/80"
                            >
                                {{ step.title }}
                            </p>
                        </article>
                    </div>
                    <p
                        class="mt-8 text-center text-xs text-[#b8e3d8] md:text-left"
                    >
                        The point is not more data. It is a more useful next
                        lesson.
                        <Link
                            href="/blog/assessment-to-next-lesson"
                            class="ml-2 font-medium text-[#b8e3d8] underline decoration-[#b8e3d8]/50 hover:text-white"
                            >Read the pillar guide →</Link
                        >
                    </p>
                </section>
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.14)"
            >
                <section
                    class="my-12 rounded-2xl border border-border/70 bg-card px-6 py-8 text-center sm:my-16 sm:px-10"
                    aria-labelledby="testimonial-heading"
                >
                    <p
                        class="text-xs font-medium tracking-[0.16em] text-primary uppercase"
                    >
                        What teachers say
                    </p>
                    <blockquote
                        id="testimonial-heading"
                        class="mx-auto mt-4 max-w-2xl font-serif text-xl leading-relaxed text-foreground sm:text-2xl"
                    >
                        “LSI cut our grading time by half and students finally
                        get feedback while the lesson is still fresh. The
                        Library Hub alone saved us hours of printing reviewers.”
                    </blockquote>
                    <p class="mt-4 text-sm font-medium text-muted-foreground">
                        Maria Santos — Grade 8 Mathematics, Davao Central
                        College
                    </p>
                    <div
                        class="mt-3 flex items-center justify-center gap-1 text-amber-500"
                        aria-label="5 out of 5 stars"
                    >
                        <span aria-hidden="true">★★★★★</span>
                        <span class="sr-only">5 out of 5</span>
                    </div>
                </section>
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.16)"
            >
                <PricingSection
                    :auth="$page.props.auth"
                    :dashboard="() => dashboard().url"
                    :register="() => register().url"
                    :is-coarse-pointer="isLowEndDevice"
                    :prefers-reduced-motion="effectiveReducedMotion"
                />
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.2)"
            >
                <section
                    id="faq"
                    class="welcome-faq scroll-mt-32 border-y border-border/70 py-16 sm:py-20"
                    aria-labelledby="faq-heading"
                >
                    <h2
                        id="faq-heading"
                        class="text-center font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                    >
                        Questions, answered.
                    </h2>
                    <div
                        class="mx-auto mt-8 max-w-3xl divide-y divide-border/70 border-y border-border/70"
                    >
                        <details
                            v-for="faq in faqs"
                            :key="faq.question"
                            class="group py-5"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-6 text-sm font-medium text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
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

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :in-view="
                    effectiveReducedMotion ? undefined : { opacity: 1, y: 0 }
                "
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition(0.24)"
            >
                <section
                    id="contact"
                    class="welcome-cta mt-16 rounded-2xl bg-[#17201f] px-6 py-10 text-[#f8f7f2] sm:mt-20 sm:px-10 sm:py-12 lg:px-12"
                    aria-labelledby="cta-heading"
                >
                    <div
                        class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-[#b8e3d8] uppercase"
                            >
                                Start with the next lesson
                            </p>
                            <h2
                                id="cta-heading"
                                class="mt-3 max-w-xl font-serif text-3xl leading-tight tracking-[-0.03em] sm:text-4xl"
                            >
                                If assessment matters to your school, let’s
                                talk.
                            </h2>
                            <p
                                class="mt-4 text-sm text-[#f8f7f2]/70 sm:text-base"
                            >
                                Start with a teacher, a class, or a whole
                                school.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="$page.props.auth?.user"
                                :href="dashboard().url"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#b8e3d8] px-5 text-sm font-semibold text-[#17201f] transition-colors hover:bg-[#d3f0e7] focus-visible:ring-2 focus-visible:ring-[#b8e3d8] focus-visible:ring-offset-2 focus-visible:ring-offset-[#17201f]"
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
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#b8e3d8] px-5 text-sm font-semibold text-[#17201f] transition-colors hover:bg-[#d3f0e7] focus-visible:ring-2 focus-visible:ring-[#b8e3d8] focus-visible:ring-offset-2 focus-visible:ring-offset-[#17201f]"
                            >
                                Create a free account
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                href="mailto:poweredbyrazer022@dccp.edu.ph?subject=LSI%20school%20pricing"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#f8f7f2]/45 px-5 text-sm font-medium text-[#f8f7f2] transition-colors hover:border-[#f8f7f2] hover:bg-[#f8f7f2]/10 focus-visible:ring-2 focus-visible:ring-[#f8f7f2] focus-visible:ring-offset-2 focus-visible:ring-offset-[#17201f]"
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
