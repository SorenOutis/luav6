<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import CapabilitiesBento from '@/components/welcome/CapabilitiesBento.vue';
import EchoPromptCta from '@/components/welcome/EchoPromptCta.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import PricingSection from '@/components/welcome/PricingSection.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';
import SafetyBand from '@/components/welcome/SafetyBand.vue';
import SectionRail from '@/components/welcome/SectionRail.vue';
import StatsBand from '@/components/welcome/StatsBand.vue';
import TestimonialMarquee from '@/components/welcome/TestimonialMarquee.vue';
import UpdatesList from '@/components/welcome/UpdatesList.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import WelcomeManifesto from '@/components/welcome/WelcomeManifesto.vue';
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
        <SectionRail />

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

            <WelcomeManifesto
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

            <CapabilitiesBento />

            <SafetyBand :prefers-reduced-motion="effectiveReducedMotion" />

            <StatsBand :prefers-reduced-motion="effectiveReducedMotion" />

            <TestimonialMarquee
                :prefers-reduced-motion="effectiveReducedMotion"
            />

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
                <div
                    class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_2fr] lg:gap-16 xl:max-w-7xl"
                >
                    <div>
                        <span
                            class="rv inline-flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-muted-foreground uppercase"
                            :style="{ '--rv-i': 0 }"
                        >
                            <span class="text-brand">07</span>
                            <span
                                class="h-px w-8 bg-border"
                                aria-hidden="true"
                            ></span>
                            FAQ
                        </span>
                        <h2
                            id="faq-heading"
                            class="rv mt-4 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl"
                            :style="{ '--rv-i': 1 }"
                        >
                            Questions, answered.
                        </h2>
                        <p
                            class="rv mt-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                            :style="{ '--rv-i': 2 }"
                        >
                            Have questions not covered here? Reach out directly
                            to our team.
                        </p>
                    </div>

                    <div
                        class="divide-y divide-border/70 border-y border-border/70"
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
                </div>
            </RevealGroup>

            <UpdatesList />

            <div id="contact" class="scroll-mt-32 pt-8 sm:pt-12">
                <EchoPromptCta
                    :auth="$page.props.auth"
                    :can-register="props.canRegister"
                    :dashboard="() => dashboard().url"
                    :login="() => login().url"
                    :register="() => register().url"
                    :prefers-reduced-motion="effectiveReducedMotion"
                />
            </div>

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
