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
        id: 'account',
        title: '1. Account responsibility',
        body: 'You are responsible for maintaining the confidentiality of your account credentials and for activity under your account. If you believe your account was accessed without permission, change your password and contact your school or our support team at once.',
    },
    {
        id: 'acceptable-use',
        title: '2. Acceptable use',
        body: 'You agree not to abuse, disrupt, scrape, reverse engineer, or attempt unauthorized access to platform services, data, or accounts. Automated collection of other users data is not allowed.',
    },
    {
        id: 'content',
        title: '3. Content and conduct',
        body: 'You retain ownership of content you submit, such as answers, messages, and support tickets. You grant LSI permission to process and display that content to provide core features such as grading, feedback, leaderboards, and support. Public shoutouts are shown without your name on the feed, but posts that break the rules may be reviewed and removed.',
    },
    {
        id: 'learning-features',
        title: '4. Learning features and AI drafts',
        body: 'AI features only draft content. Generated questions, essay grades, and feedback suggestions land in a teacher review queue and must be explicitly approved or rejected by a teacher. No AI write happens on its own. Teacher decisions on grades and feedback are final inside the platform.',
    },
    {
        id: 'availability',
        title: '5. Availability',
        body: 'We may update, suspend, or discontinue parts of the service without notice, and uninterrupted uptime is not guaranteed. Scheduled maintenance windows may temporarily limit access.',
    },
    {
        id: 'liability',
        title: '6. Limitation of liability',
        body: 'The platform is provided on an as is basis and is not liable for indirect, incidental, or consequential damages. Nothing in these terms limits liability where the law does not allow it.',
    },
    {
        id: 'privacy',
        title: '7. Privacy',
        body: 'How we collect, use, share, and delete your data is described in the Privacy Policy, which forms part of these terms. By using LSI you also accept the Privacy Policy and the Cookie Policy.',
    },
    {
        id: 'changes',
        title: '8. Changes to these terms',
        body: 'These terms may be revised from time to time. Continued use after updates means you accept the revised terms. The effective date is shown on this page.',
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
                name: 'Terms and Conditions',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/terms`
                        : 'https://lsi.koamishin.com/terms',
            },
        ],
    },
]);
</script>

<template>
    <Head title="Terms and Conditions | LSI - KOAMISHIN" />
    <SeoHead
        title="Terms and Conditions | LSI - KOAMISHIN"
        description="The rules for using LSI accounts, content, learning features, and support."
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
                Terms and Conditions
            </h1>
            <p class="mt-4 text-sm text-muted-foreground">
                Effective date: September 12, 2026.
            </p>
            <p
                class="mt-6 text-sm leading-relaxed text-muted-foreground sm:text-base"
            >
                These terms govern your use of LSI. By creating an account or
                signing in with Google or GitHub, you accept these terms, the
                Privacy Policy, and the Cookie Policy.
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
                    <p
                        v-if="section.id === 'privacy'"
                        class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm"
                    >
                        <Link
                            href="/privacy"
                            class="font-medium text-primary underline decoration-primary/50 underline-offset-4 hover:text-foreground"
                        >
                            Read the Privacy Policy
                        </Link>
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
                aria-labelledby="terms-contact-heading"
            >
                <h2
                    id="terms-contact-heading"
                    class="font-serif text-2xl tracking-[-0.03em]"
                >
                    Questions about these terms?
                </h2>
                <p class="mt-3 text-sm text-[#f8f7f2]/70">
                    Contact us and we will help.
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
                        href="mailto:hello@koamishin.dev?subject=LSI%20terms%20question"
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
