<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import PricingSection from '@/components/welcome/PricingSection.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';
import RolePillSwitcher from '@/components/welcome/RolePillSwitcher.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import WelcomeStatement from '@/components/welcome/WelcomeStatement.vue';
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

const faqs = [
    {
        question: 'What does LSI stand for?',
        answer: 'Learning Systems Intelligence — built for schools that want assessment to drive the next lesson, not just a score. Teachers stay mandatory reviewers, approving every AI-assisted remark before it reaches a learner.',
    },
    {
        question: 'Who is LSI for?',
        answer: 'Teachers get section-targeted exams, auto-graded objective items, and AI-drafted essay feedback to review. Learners get immediate feedback with XP, levels, and section leaderboards. Schools get a tenant-isolated workspace with progress, grades, and audit trails.',
    },
    {
        question: 'Do teachers stay in control?',
        answer: 'Yes. AI only drafts — question sets, grades, suggested feedback — and every output lands in a review queue as a pending action. Nothing is written autonomously; the human-approval boundary is enforced by nonce-protected endpoints.',
    },
    {
        question: 'How is learner data handled?',
        answer: 'Tenant data is isolated per Workspace, so teachers see only their sections and learners only their enrolled courses. AI access is logged with workspace budgets, schools retain ownership, and public pages are noindex where appropriate.',
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
            name: 'Maria Santos, Grade 8 Math',
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
            class="mx-auto flex max-w-[1440px] flex-col px-4 pt-2 pb-16 sm:px-6 sm:pt-4 sm:pb-24 lg:px-8 lg:pt-4 lg:pb-32 xl:px-12"
        >
            <WelcomeHero
                :can-register="props.canRegister"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
                :register="() => register().url"
                :is-booted="true"
                :prefers-reduced-motion="prefersReducedMotion"
            />

            <WelcomeStatement
                :prefers-reduced-motion="effectiveReducedMotion"
            />

            <div id="how-it-works" class="scroll-mt-32">
                <FeatureCards
                    :is-coarse-pointer="isLowEndDevice"
                    :prefers-reduced-motion="effectiveReducedMotion"
                    :auth="$page.props.auth"
                    :dashboard="() => dashboard().url"
                    :login="() => login().url"
                />
            </div>

            <RolePillSwitcher :prefers-reduced-motion="prefersReducedMotion" />

            <RevealGroup
                as="section"
                :stagger="90"
                class="surface-card mx-auto my-20 w-full max-w-6xl overflow-hidden rounded-3xl border border-border/80 bg-card p-6 shadow-xs sm:my-24 sm:p-10 lg:p-12 xl:max-w-7xl"
                aria-labelledby="field-note-heading"
            >
                <div
                    class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <blockquote
                            id="field-note-heading"
                            class="rv mt-4 font-sans text-xl leading-relaxed font-normal text-foreground sm:text-2xl"
                            :style="{ '--rv-i': 0 }"
                        >
                            “LSI cut our grading turnaround by half. Students
                            receive feedback while the lesson is still fresh in
                            their minds, and our teachers retain 100% approval
                            authority over every AI draft.”
                        </blockquote>
                        <div
                            class="rv mt-5 flex items-center gap-3"
                            :style="{ '--rv-i': 1 }"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/15 font-bold text-brand"
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
                                    Grade 8 Mathematics Head
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Real metric badges -->
                    <div
                        class="grid grid-cols-2 gap-4 border-t border-border/70 pt-6 sm:grid-cols-3 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10"
                    >
                        <div
                            class="rv rv-pop space-y-1"
                            :style="{ '--rv-i': 2 }"
                        >
                            <p
                                class="font-sans text-3xl font-bold tracking-tight text-foreground"
                            >
                                50%+
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Time saved on item grading
                            </p>
                        </div>
                        <div
                            class="rv rv-pop space-y-1"
                            :style="{ '--rv-i': 3 }"
                        >
                            <p
                                class="font-sans text-3xl font-bold tracking-tight text-foreground"
                            >
                                &lt; 2 hrs
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Feedback dispatch speed
                            </p>
                        </div>
                        <div
                            class="rv rv-pop col-span-2 space-y-1 sm:col-span-1"
                            :style="{ '--rv-i': 4 }"
                        >
                            <p
                                class="font-sans text-3xl font-bold tracking-tight text-brand"
                            >
                                100%
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Teacher approval control
                            </p>
                        </div>
                    </div>
                </div>
            </RevealGroup>

            <PricingSection
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :register="() => register().url"
                :is-coarse-pointer="isLowEndDevice"
                :prefers-reduced-motion="effectiveReducedMotion"
            />

            <RevealGroup
                as="section"
                id="faq"
                :stagger="70"
                class="welcome-faq scroll-mt-32 border-y border-border/70 py-20 sm:py-28"
                aria-labelledby="faq-heading"
            >
                <h2
                    id="faq-heading"
                    class="rv text-center font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
                    :style="{ '--rv-i': 0 }"
                >
                    Questions, answered.
                </h2>
                <div
                    class="mx-auto mt-8 max-w-4xl divide-y divide-border/70 border-y border-border/70"
                >
                    <details
                        v-for="(faq, index) in faqs"
                        :key="faq.question"
                        class="rv group py-5"
                        :style="{ '--rv-i': index + 1 }"
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
                            class="max-w-3xl pt-3 pr-10 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </RevealGroup>

            <RevealGroup
                as="section"
                id="contact"
                class="welcome-cta relative mx-auto mt-16 w-full max-w-6xl overflow-hidden rounded-3xl border border-border/80 bg-card px-6 py-14 text-foreground shadow-xs sm:mt-24 sm:px-10 sm:py-20 lg:px-14 xl:max-w-7xl"
                aria-labelledby="cta-heading"
            >
                <!-- Giant outline Echo watermark -->
                <div
                    class="rv pointer-events-none absolute -right-6 -bottom-16 z-0 origin-bottom-right scale-[1.8] opacity-[0.08] select-none sm:-bottom-20"
                    :style="{ '--rv-i': 2 }"
                    aria-hidden="true"
                >
                    <ChatAiOrb
                        size="lg"
                        variant="outline"
                        state="micro"
                        :delay="1500"
                        color="var(--color-brand)"
                    />
                </div>

                <div
                    class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <p
                            class="rv font-mono text-xs font-medium tracking-wider text-brand uppercase"
                            :style="{ '--rv-i': 0 }"
                        >
                            Get started
                        </p>
                        <h2
                            id="cta-heading"
                            class="rv mt-3 max-w-xl font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
                            :style="{ '--rv-i': 1 }"
                        >
                            If assessment matters to your school, let’s talk.
                        </h2>
                        <p
                            class="rv mt-4 text-sm font-normal text-muted-foreground sm:text-base"
                            :style="{ '--rv-i': 2 }"
                        >
                            Start with a teacher, a class, or a whole school.
                        </p>
                    </div>
                    <div
                        class="rv rv-pop flex flex-col gap-3 sm:flex-row"
                        :style="{ '--rv-i': 3 }"
                    >
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="dashboard().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-sm font-semibold text-background shadow-xs transition-all hover:opacity-90 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                        >
                            Open dashboard
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            v-else-if="props.canRegister"
                            :href="register().url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-sm font-semibold text-background shadow-xs transition-all hover:opacity-90 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                        >
                            Create free account
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            :href="login().url"
                            class="inline-flex min-h-12 items-center justify-center rounded-full border border-border/80 bg-card px-6 text-sm font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                        >
                            Sign in
                        </Link>
                    </div>
                </div>
            </RevealGroup>

            <RevealGroup
                as="section"
                aria-label="Built by KOAMISHIN collective"
                class="mt-16 text-center sm:mt-20"
            >
                <span
                    class="rv rv-pop inline-flex items-center gap-1.5 rounded-full border border-border/80 bg-card px-3.5 py-1 font-mono text-[11px] font-medium text-muted-foreground"
                    :style="{ '--rv-i': 0 }"
                >
                    Open Source · Laravel 12 · AGPL-3.0
                </span>
                <p
                    class="rv mx-auto mt-4 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                    :style="{ '--rv-i': 1 }"
                >
                    Built by the KOAMISHIN collective — crafted in the open,
                    free to fork.
                </p>
                <div
                    class="rv mt-5 flex flex-col items-center justify-center gap-3 sm:flex-row"
                    :style="{ '--rv-i': 2 }"
                >
                    <a
                        href="https://koamishin.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-medium text-background shadow-xs transition-all hover:opacity-90 active:scale-[0.98]"
                    >
                        Explore koamishin.com
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <a
                        href="https://github.com/koamishin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center justify-center rounded-full border border-border/80 bg-card px-6 text-sm font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
                    >
                        GitHub collective
                    </a>
                </div>
            </RevealGroup>
        </main>

        <WelcomeFooter />
    </div>
</template>
