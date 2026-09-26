<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import DualPerspectiveShowcase from '@/components/welcome/DualPerspectiveShowcase.vue';
import EchoInteractiveDemo from '@/components/welcome/EchoInteractiveDemo.vue';
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
        class="welcome-root mobile-ui-page min-h-screen overflow-x-hidden bg-background font-sans text-foreground selection:bg-primary/20"
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
                :transition="revealTransition(0.06)"
            >
                <EchoInteractiveDemo />
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
                <DualPerspectiveShowcase />
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
                    class="surface-card my-16 overflow-hidden rounded-2xl border border-border/80 bg-card p-6 sm:my-20 sm:p-10 lg:p-12"
                    aria-labelledby="field-note-heading"
                >
                    <div
                        class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="max-w-2xl">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-[#D97757]/20 bg-[#D97757]/10 px-3 py-1 text-xs font-semibold tracking-wider text-[#D97757] uppercase"
                            >
                                Verified Educator Field Note
                            </div>
                            <blockquote
                                id="field-note-heading"
                                class="mt-4 font-serif text-2xl leading-relaxed text-foreground sm:text-3xl"
                            >
                                “LSI cut our grading turnaround by half.
                                Students receive feedback while the lesson is
                                still fresh in their minds, and our teachers
                                retain 100% approval authority over every AI
                                draft.”
                            </blockquote>
                            <div class="mt-5 flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#D97757]/15 font-bold text-[#D97757]"
                                >
                                    MS
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Maria Santos
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Grade 8 Mathematics Head · Davao Central
                                        College (DCCP)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Real metric badges -->
                        <div
                            class="grid grid-cols-2 gap-4 border-t border-border/70 pt-6 sm:grid-cols-3 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10"
                        >
                            <div class="space-y-1">
                                <p
                                    class="font-serif text-3xl font-bold tracking-tight text-foreground"
                                >
                                    50%+
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Time saved on item grading
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p
                                    class="font-serif text-3xl font-bold tracking-tight text-foreground"
                                >
                                    &lt; 2 hrs
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Feedback dispatch speed
                                </p>
                            </div>
                            <div class="col-span-2 space-y-1 sm:col-span-1">
                                <p
                                    class="font-serif text-3xl font-bold tracking-tight text-[#D97757]"
                                >
                                    100%
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Teacher approval control
                                </p>
                            </div>
                        </div>
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
                    class="welcome-cta relative mt-16 overflow-hidden rounded-2xl border border-border/80 bg-card px-6 py-10 text-foreground shadow-xl sm:mt-20 sm:px-10 sm:py-14 lg:px-14"
                    aria-labelledby="cta-heading"
                >
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
                                id="cta-heading"
                                class="mt-3 max-w-xl font-serif text-3xl leading-tight tracking-[-0.03em] text-foreground sm:text-4xl"
                            >
                                If assessment matters to your school, let’s
                                talk.
                            </h2>
                            <p
                                class="mt-4 text-sm text-muted-foreground sm:text-base"
                            >
                                Start with a teacher, a class, or a whole
                                school.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="$page.props.auth?.user"
                                :href="dashboard().url"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
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
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#D97757] px-6 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                            >
                                Create a free account
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <Link
                                :href="login().url"
                                class="inline-flex min-h-12 items-center justify-center rounded-xl border border-border/80 bg-secondary/30 px-5 text-sm font-medium text-foreground transition-colors hover:bg-secondary/60 focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                            >
                                Sign in
                            </Link>
                        </div>
                    </div>
                </section>
            </Motion>
        </main>

        <WelcomeFooter />
    </div>
</template>
