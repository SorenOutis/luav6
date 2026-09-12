<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const cookieRows = [
    {
        name: 'laravel_session, XSRF-TOKEN',
        purpose:
            'Sign in state and form security. Required for the app to work.',
        expiry: 'Session or up to 2 hours',
    },
    {
        name: 'remember_me (optional)',
        purpose:
            'Keeps you signed in on this device when you tick Remember me.',
        expiry: 'Up to 1 year, only when selected',
    },
    {
        name: 'appearance, themePreset, fontPreset, cardStylePreset',
        purpose: 'Remember your theme and display preferences.',
        expiry: 'Up to 1 year',
    },
    {
        name: 'sidebar_state',
        purpose: 'Remember whether the sidebar is open or collapsed.',
        expiry: '7 days',
    },
];

const storageRows = [
    {
        name: 'appearance, themePreset, fontPreset, cardStylePreset, dyslexia-friendly',
        purpose:
            'Same display preferences, kept in this browser for faster loading.',
    },
    {
        name: 'leaderboard-selected-section-id, leaderboard-blurred-sections',
        purpose: 'Remember your leaderboard view on this device.',
    },
    {
        name: 'exam_draft_*',
        purpose:
            'Autosave of in-progress exam answers on this device so work is not lost.',
    },
    {
        name: 'onboarding progress, session caches',
        purpose:
            'Remember completed tours and speed up repeat visits. Cleared on sign out where applicable.',
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
                name: 'Cookie Policy',
                item:
                    typeof window !== 'undefined'
                        ? `${window.location.origin}/cookies`
                        : 'https://lsi.koamishin.com/cookies',
            },
        ],
    },
]);
</script>

<template>
    <Head title="Cookie Policy | LSI - KOAMISHIN" />
    <SeoHead
        title="Cookie Policy | LSI - KOAMISHIN"
        description="Which cookies and local storage LSI uses, why each one exists, and how to control them."
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
                Cookie Policy
            </h1>
            <p class="mt-4 text-sm text-muted-foreground">
                Effective date: September 12, 2026.
            </p>
            <p
                class="mt-6 text-sm leading-relaxed text-muted-foreground sm:text-base"
            >
                LSI uses a small set of cookies and browser storage so sign in,
                security, and preferences work. There is no advertising
                tracking. The banner on your first visit records your choice.
            </p>

            <section class="mt-10" aria-labelledby="cookies-table-heading">
                <h2
                    id="cookies-table-heading"
                    class="text-base font-semibold text-foreground"
                >
                    1. Cookies we set
                </h2>
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border/60"
                >
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-border/60 bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-3 font-semibold">Cookie</th>
                                <th class="px-4 py-3 font-semibold">Purpose</th>
                                <th class="px-4 py-3 font-semibold">Expiry</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="row in cookieRows" :key="row.name">
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ row.name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ row.purpose }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ row.expiry }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-10" aria-labelledby="storage-table-heading">
                <h2
                    id="storage-table-heading"
                    class="text-base font-semibold text-foreground"
                >
                    2. Browser storage we use
                </h2>
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border/60"
                >
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-border/60 bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-3 font-semibold">Key</th>
                                <th class="px-4 py-3 font-semibold">Purpose</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="row in storageRows" :key="row.name">
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ row.name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ row.purpose }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-10" aria-labelledby="third-party-heading">
                <h2
                    id="third-party-heading"
                    class="text-base font-semibold text-foreground"
                >
                    3. Third party services
                </h2>
                <p
                    class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    Fonts load from a font CDN, realtime updates use a websocket
                    provider, embedded videos may come from YouTube or Vimeo,
                    and social sign in uses Google or GitHub when you choose it.
                    These providers may receive technical data such as your IP
                    address to deliver their service. We do not share data with
                    advertisers.
                </p>
            </section>

            <section class="mt-10" aria-labelledby="control-heading">
                <h2
                    id="control-heading"
                    class="text-base font-semibold text-foreground"
                >
                    4. How to control cookies
                </h2>
                <p
                    class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    Use the cookie banner to accept all cookies or keep
                    essential ones only. You can change your choice at any time
                    with the Cookie settings button in the footer. You can also
                    block cookies in your browser settings, but sign in and core
                    features will stop working because they depend on strictly
                    necessary cookies. Signing out clears user-specific
                    preferences stored by the app.
                </p>
                <p class="mt-4 text-sm">
                    <Link
                        href="/privacy"
                        class="font-medium text-primary underline decoration-primary/50 underline-offset-4 hover:text-foreground"
                    >
                        Read the Privacy Policy
                    </Link>
                </p>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>
