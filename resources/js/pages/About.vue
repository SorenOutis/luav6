<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    ArrowRight,
    Check,
    ClipboardList,
    Lightbulb,
    MessageSquare,
    School,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
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
const reduceMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

const principles = [
    {
        icon: Check,
        title: 'Useful before impressive',
        body: 'Every part of LSI should make classroom work clearer.',
    },
    {
        icon: UserRound,
        title: 'Teacher control',
        body: 'Feedback stays reviewable, adjustable, and yours to approve.',
    },
    {
        icon: ShieldCheck,
        title: 'Privacy by default',
        body: 'Schools keep ownership of their content and learner data.',
    },
];

const audiences = [
    {
        icon: UserRound,
        title: 'Teachers',
        body: 'Create, review, and plan with less friction.',
    },
    {
        icon: MessageSquare,
        title: 'Learners',
        body: 'Get feedback that helps them keep going.',
    },
    {
        icon: School,
        title: 'Schools',
        body: 'See what is happening across classes and cohorts.',
    },
];

const faqs = [
    {
        question: 'What does LSI stand for?',
        answer: 'LSI is a learning platform built around the work that happens after an assessment: understanding responses and deciding what to do next.',
    },
    {
        question: 'Who is LSI for?',
        answer: 'LSI is designed for teachers, learners, and schools that want a clearer connection between assessment and follow-up.',
    },
    {
        question: 'Do teachers stay in control?',
        answer: 'Yes. Teachers review and approve feedback and recommendations before they reach learners.',
    },
    {
        question: 'How is learner data handled?',
        answer: 'LSI is designed around school ownership and practical, reviewable use of learner information.',
    },
];

const seoJsonLd = computed(() => [
    {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        '@id': 'https://koamishin.dev/#organization',
        name: 'LSI - KOAMISHIN',
        alternateName: 'LSI',
        description:
            'A school-ready learning platform that helps teachers turn assessments into clear next steps.',
        url:
            typeof window !== 'undefined'
                ? window.location.origin
                : 'https://koamishin.dev',
        logo: {
            '@type': 'ImageObject',
            url:
                typeof window !== 'undefined'
                    ? `${window.location.origin}/brand/og-cover.png`
                    : 'https://koamishin.dev/brand/og-cover.png',
            width: 1200,
            height: 630,
        },
        sameAs: ['https://github.com/SorenOutis/luav6'],
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
                        : 'https://koamishin.dev/',
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'About',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/about`
                        : 'https://koamishin.dev/about',
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
        class="about-root min-h-screen overflow-x-hidden bg-[#f8f7f2] font-sans text-[#17201f] selection:bg-primary/20 dark:bg-background dark:text-foreground"
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
            <section
                class="grid items-center gap-10 border-b border-border/70 pb-16 sm:gap-14 sm:pb-20 lg:grid-cols-[1fr_0.9fr] lg:gap-20 lg:pb-24"
                aria-labelledby="about-heading"
            >
                <Motion
                    :initial="reduceMotion ? false : { opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="revealTransition()"
                    class="max-w-2xl"
                >
                    <p
                        class="mb-7 text-xs font-medium tracking-[0.16em] text-primary uppercase"
                    >
                        About LSI
                    </p>
                    <h1
                        id="about-heading"
                        class="max-w-2xl font-serif text-[3.25rem] leading-[0.95] tracking-[-0.055em] text-foreground sm:text-6xl lg:text-[5.6rem]"
                    >
                        We build tools for the next lesson.
                    </h1>
                    <p
                        class="mt-8 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        LSI helps teachers turn classroom evidence into clearer
                        decisions, useful feedback, and better follow-up.
                    </p>
                    <p class="mt-5 text-sm font-medium text-foreground/80">
                        Built for teachers. Designed for schools.
                    </p>
                </Motion>

                <Motion
                    :initial="reduceMotion ? false : { opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="revealTransition(0.08)"
                    class="flex justify-center lg:justify-end"
                >
                    <div
                        class="w-full max-w-[430px] rotate-[1deg] border border-border/50 bg-card px-7 py-8 shadow-[0_18px_28px_-20px_rgba(26,26,30,0.5)] sm:px-10 sm:py-10"
                    >
                        <p
                            class="font-serif text-xl tracking-[0.04em] text-foreground"
                        >
                            A BETTER QUESTION
                        </p>
                        <div class="mt-2 h-px w-36 bg-foreground/70"></div>
                        <div class="mt-9 space-y-7">
                            <div class="flex gap-4">
                                <ClipboardList
                                    class="mt-1 h-5 w-5 shrink-0 text-primary"
                                    stroke-width="1.4"
                                    aria-hidden="true"
                                />
                                <p
                                    class="font-serif text-xl leading-snug text-foreground"
                                >
                                    What did learners understand?
                                </p>
                            </div>
                            <div class="flex gap-4">
                                <Lightbulb
                                    class="mt-1 h-5 w-5 shrink-0 text-primary"
                                    stroke-width="1.4"
                                    aria-hidden="true"
                                />
                                <p
                                    class="font-serif text-xl leading-snug text-foreground"
                                >
                                    What should we teach next?
                                </p>
                            </div>
                        </div>
                        <p
                            class="mt-10 border-t border-border/60 pt-4 text-xs text-muted-foreground"
                        >
                            Assessment is the beginning of the conversation.
                        </p>
                    </div>
                </Motion>
            </section>

            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="grid gap-10 border-b border-border/70 py-16 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20"
                    aria-labelledby="why-heading"
                >
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.16em] text-primary uppercase"
                        >
                            Why LSI exists
                        </p>
                        <h2
                            id="why-heading"
                            class="mt-4 max-w-xl font-serif text-3xl leading-[1.08] tracking-[-0.04em] text-foreground sm:text-4xl lg:text-5xl"
                        >
                            Assessment should help the next lesson.
                        </h2>
                        <p
                            class="mt-6 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            Too often, assessment ends with a score. LSI helps
                            teachers see the response, understand the pattern,
                            and decide what to do while learning is still
                            happening.
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
                                >{{ index + 1 }}</span
                            >
                            <span class="font-serif text-xl text-foreground">{{
                                step
                            }}</span>
                        </li>
                    </ol>
                </section>
            </Motion>

            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    class="border-b border-border/70 py-16 sm:py-20"
                    aria-labelledby="principles-heading"
                >
                    <h2
                        id="principles-heading"
                        class="text-center font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                    >
                        What guides the work.
                    </h2>
                    <div
                        class="mt-10 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
                    >
                        <article
                            v-for="principle in principles"
                            :key="principle.title"
                            class="flex gap-5 px-2 py-7 sm:px-6 md:flex-col md:py-8 lg:px-8"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-foreground/70 text-foreground"
                            >
                                <component
                                    :is="principle.icon"
                                    class="h-5 w-5"
                                    stroke-width="1.5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ principle.title }}
                                </h3>
                                <p
                                    class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ principle.body }}
                                </p>
                            </div>
                        </article>
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
                    class="border-b border-border/70 py-16 sm:py-20"
                    aria-labelledby="audience-heading"
                >
                    <h2
                        id="audience-heading"
                        class="text-center font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
                    >
                        Built around the people doing the work.
                    </h2>
                    <div
                        class="mt-10 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
                    >
                        <article
                            v-for="audience in audiences"
                            :key="audience.title"
                            class="flex flex-col items-center px-6 py-8 text-center"
                        >
                            <component
                                :is="audience.icon"
                                class="h-10 w-10 text-foreground"
                                stroke-width="1.35"
                                aria-hidden="true"
                            />
                            <h3
                                class="mt-5 text-base font-semibold text-foreground"
                            >
                                {{ audience.title }}
                            </h3>
                            <p
                                class="mt-2 max-w-[180px] text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ audience.body }}
                            </p>
                        </article>
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
                    class="my-16 rounded-2xl bg-[#17201f] px-5 py-8 text-[#f8f7f2] sm:my-20 sm:px-10 sm:py-10"
                    aria-labelledby="loop-heading"
                >
                    <h2
                        id="loop-heading"
                        class="font-serif text-2xl tracking-[-0.03em] sm:text-3xl"
                    >
                        From response to next step.
                    </h2>
                    <div class="mt-8 grid gap-8 md:grid-cols-3 md:gap-4">
                        <article
                            v-for="(step, index) in [
                                {
                                    icon: ClipboardList,
                                    text: 'A learner answers.',
                                },
                                {
                                    icon: MessageSquare,
                                    text: 'A teacher sees the pattern.',
                                },
                                {
                                    icon: Lightbulb,
                                    text: 'The next lesson gets clearer.',
                                },
                            ]"
                            :key="step.text"
                            class="relative flex flex-col items-center text-center md:items-start md:text-left"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full border border-primary/70 text-[#b8e3d8]"
                            >
                                <component
                                    :is="step.icon"
                                    class="h-5 w-5"
                                    stroke-width="1.4"
                                    aria-hidden="true"
                                />
                            </div>
                            <ArrowRight
                                v-if="index < 2"
                                class="hidden h-4 w-4 text-primary md:absolute md:top-6 md:right-5 md:block"
                                aria-hidden="true"
                            />
                            <p
                                class="mt-4 max-w-xs text-sm leading-relaxed text-[#f8f7f2]/80"
                            >
                                {{ step.text }}
                            </p>
                        </article>
                    </div>
                    <p
                        class="mt-8 text-center text-xs text-[#b8e3d8] md:text-left"
                    >
                        The point is not more data. It is a more useful next
                        lesson.
                    </p>
                </section>
            </Motion>

            <Motion
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="faq"
                    class="scroll-mt-32 border-y border-border/70 py-16 sm:py-20"
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
                :initial="reduceMotion ? false : { opacity: 0, y: 24 }"
                :in-view="reduceMotion ? undefined : { opacity: 1, y: 0 }"
                :in-view-options="{ once: true, margin: '-80px' }"
                :transition="revealTransition()"
            >
                <section
                    id="contact"
                    class="mt-16 scroll-mt-32 rounded-2xl bg-[#17201f] px-6 py-10 text-[#f8f7f2] sm:mt-20 sm:px-10 sm:py-12 lg:px-12"
                    aria-labelledby="contact-heading"
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
                                id="contact-heading"
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
                                href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#f8f7f2]/45 px-5 text-sm font-medium text-[#f8f7f2] transition-colors hover:border-[#f8f7f2] hover:bg-[#f8f7f2]/10 focus-visible:ring-2 focus-visible:ring-[#f8f7f2] focus-visible:ring-offset-2 focus-visible:ring-offset-[#17201f]"
                                >Contact sales</a
                            >
                        </div>
                    </div>
                </section>
            </Motion>
        </main>

        <WelcomeFooter />
    </div>
</template>

<style scoped>
details > summary::-webkit-details-marker {
    display: none;
}
</style>
