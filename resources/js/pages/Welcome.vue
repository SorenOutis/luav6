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
        : { duration: 0.55, easing: [0.23, 1, 0.32, 1], delay };

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

const webSiteJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'LSI - KOAMISHIN',
    alternateName: 'LSI',
    description:
        'A school-ready learning platform that helps teachers turn assessments into clear next steps.',
};
</script>

<template>
    <Head title="LSI - KOAMISHIN | Make every assessment count" />
    <SeoHead
        description="LSI helps teachers see what learners understand, give useful feedback, and plan what to teach next."
        type="website"
        :jsonld="webSiteJsonLd"
    />

    <div
        class="welcome-root min-h-screen overflow-x-hidden bg-[#f8f7f2] font-sans text-[#17201f] selection:bg-primary/20 dark:bg-background dark:text-foreground"
    >
        <WelcomeHeader
            :can-register="props.canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
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
                :while-in-view="
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
                :while-in-view="
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
                :while-in-view="
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
                :while-in-view="
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
                        From response to next step.
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
                    </p>
                </section>
            </Motion>

            <Motion
                :initial="
                    effectiveReducedMotion ? false : { opacity: 0, y: 24 }
                "
                :while-in-view="
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
                :while-in-view="
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
                :while-in-view="
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
                                href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
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
