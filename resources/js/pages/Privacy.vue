<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import { dashboard, login, register } from '@/routes';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const sections = [
    {
        id: 'who-we-are',
        title: '1. Who we are',
        body: 'LSI (Learning Systems Intelligence) is built by KOAMISHIN for schools that want assessment to drive the next lesson. For privacy questions, contact us at hello@koamishin.dev. Your school may also have its own data protection contact, and school policies apply alongside this policy.',
    },
    {
        id: 'data-we-collect',
        title: '2. Data we collect',
        body: 'We collect only what the platform needs to work. Account data: your first name, last name, optional middle name, email address, and password (stored hashed). Profile data you choose to add: bio, avatar, cover photo, and profile visibility settings. Learning data: sections, courses, exams, assignments, answers, grades, feedback, XP, streaks, leaderboard entries, and chat conversations with the learning assistant. Support data: tickets, replies, and files you attach. Technical data: login records, device appearance preferences, and standard server logs needed for security.',
    },
    {
        id: 'how-we-use',
        title: '3. How we use your data',
        body: 'We use your data to run your account, show your progress and grades, give feedback on your work, keep the platform secure, and answer support requests. We do not sell your data. We do not use learner data for advertising or for profiling unrelated to learning.',
    },
    {
        id: 'sharing',
        title: '4. Who can see your data',
        body: 'Teachers see the work of learners in their own sections. School workspaces are isolated, so one school cannot see another school data. Service providers process data only to run the platform: realtime updates, fonts, social sign in through Google or GitHub when you choose it, and AI providers that draft essay feedback for teacher review. Every AI draft must be approved by a teacher before it reaches a learner.',
    },
    {
        id: 'cookies',
        title: '5. Cookies and local storage',
        body: 'We use strictly necessary cookies for sign in and security, plus functional storage for preferences such as theme and sidebar state. There is no advertising tracking. Details are listed in the Cookie Policy.',
    },
    {
        id: 'retention',
        title: '6. Retention and deletion',
        body: 'We keep your data while your account is active and as long as your school needs learning records. You can delete your account at any time from Settings, Profile, under Delete account. Deletion removes your account and its resources permanently and cannot be undone.',
    },
    {
        id: 'your-rights',
        title: '7. Your rights',
        body: 'You can review and update your profile in Settings, control who sees your public profile activity, ask for a copy or correction of your data, and ask for deletion. To exercise these rights, use the Support page inside the app or email hello@koamishin.dev.',
    },
    {
        id: 'learners',
        title: '8. Learners and schools',
        body: 'LSI is used in schools, so many accounts belong to learners. Schools decide who gets an account and which sections a learner joins. If you are a parent or guardian with a question about a learner account, please contact the school first, then us if needed.',
    },
    {
        id: 'changes',
        title: '9. Changes to this policy',
        body: 'We may update this policy when the platform changes. Continued use after an update means you accept the revised policy. The effective date is shown below.',
    },
];

const seoJsonLd = computed(() => [
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
                name: 'Privacy Policy',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/privacy`
                        : 'https://lsi.koamishin.com/privacy',
            },
        ],
    },
]);
</script>

<template>
    <Head title="Privacy Policy | LSI - KOAMISHIN" />
    <SeoHead
        title="Privacy Policy | LSI - KOAMISHIN"
        description="How LSI collects, uses, shares, and deletes account, learning, and support data."
        type="article"
        :jsonld="seoJsonLd"
    />

    <div
        class="min-h-screen overflow-x-hidden bg-[#f8f7f2] font-sans text-[#17201f] selection:bg-primary/20 dark:bg-background dark:text-foreground"
    >
        <WelcomeHeader
            :can-register="props.canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :branding="$page.props.schoolBranding"
            :is-booted="true"
            hide-scroll-nav
        />

        <main
            class="mx-auto flex max-w-3xl flex-col px-4 pt-8 pb-16 sm:px-6 sm:pt-12 sm:pb-24"
        >
            <p
                class="text-xs font-medium tracking-[0.16em] text-primary uppercase"
            >
                Legal
            </p>
            <h1
                class="mt-4 font-serif text-4xl leading-[1.05] tracking-[-0.04em] text-foreground sm:text-5xl"
            >
                Privacy Policy
            </h1>
            <p class="mt-4 text-sm text-muted-foreground">
                Effective date: September 12, 2026.
            </p>
            <p
                class="mt-6 text-sm leading-relaxed text-muted-foreground sm:text-base"
            >
                This policy explains what data LSI collects, how it is used, who
                can see it, and how to delete it. It applies to every account on
                the platform.
            </p>

            <div
                class="mt-10 divide-y divide-border/70 border-y border-border/70"
            >
                <section
                    v-for="section in sections"
                    :key="section.id"
                    :id="section.id"
                    class="scroll-mt-32 py-7"
                    :aria-labelledby="`${section.id}-heading`"
                >
                    <h2
                        :id="`${section.id}-heading`"
                        class="text-base font-semibold text-foreground"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ section.body }}
                    </p>
                    <p v-if="section.id === 'cookies'" class="mt-3 text-sm">
                        <Link
                            href="/cookies"
                            class="font-medium text-primary underline decoration-primary/50 underline-offset-4 hover:text-foreground"
                        >
                            Read the Cookie Policy
                        </Link>
                    </p>
                </section>
            </div>

            <section
                class="mt-12 rounded-2xl bg-[#17201f] px-6 py-8 text-[#f8f7f2] sm:px-10"
                aria-labelledby="privacy-contact-heading"
            >
                <h2
                    id="privacy-contact-heading"
                    class="font-serif text-2xl tracking-[-0.03em]"
                >
                    Questions about your data?
                </h2>
                <p class="mt-3 text-sm text-[#f8f7f2]/70">
                    Contact us and we will respond to privacy requests.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="dashboard().url"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#b8e3d8] px-5 text-sm font-semibold text-[#17201f] transition-colors hover:bg-[#d3f0e7]"
                    >
                        Open dashboard
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <a
                        href="mailto:hello@koamishin.dev?subject=LSI%20privacy%20request"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#f8f7f2]/45 px-5 text-sm font-medium text-[#f8f7f2] transition-colors hover:border-[#f8f7f2] hover:bg-[#f8f7f2]/10"
                    >
                        hello@koamishin.dev
                    </a>
                </div>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>
