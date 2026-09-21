<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import CookieConsentBanner from '@/components/CookieConsentBanner.vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
    /** Expand the card to a wider, landscape width (e.g. for side-by-side layouts). */
    wide?: boolean;
}>();

interface SchoolBranding {
    name?: string;
    logoUrl?: string | null;
}

const page = usePage();
const branding = (page.props.schoolBranding ?? {}) as SchoolBranding;
</script>

<template>
    <SeoHead noindex />
    <div
        class="theme-neutral-page relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10"
    >
        <!-- Ambient radial glow behind the card -->
        <div
            class="pointer-events-none absolute -top-24 left-1/2 -z-0 h-[480px] w-[480px] -translate-x-1/2 rounded-full bg-[#D97757]/[0.06] blur-[110px]"
            aria-hidden="true"
        ></div>

        <div
            class="relative z-10 flex w-full flex-col gap-6"
            :class="wide ? 'max-w-3xl' : 'max-w-md'"
        >
            <Link
                :href="home()"
                class="group flex flex-col items-center gap-2.5 self-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-border/80 bg-card p-1.5 shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:border-[#D97757]/50 group-hover:shadow-md"
                >
                    <img
                        v-if="branding.logoUrl"
                        :src="branding.logoUrl"
                        :alt="`${branding.name || 'School'} logo`"
                        class="h-full w-full rounded-xl object-contain"
                    />
                    <AppLogoIcon
                        v-else
                        class="size-7 fill-current text-foreground"
                    />
                </div>
                <span
                    v-if="branding.name"
                    class="max-w-[16rem] truncate text-sm font-semibold text-foreground"
                >
                    {{ branding.name }}
                </span>
            </Link>

            <div class="flex flex-col gap-6">
                <Card
                    class="surface-card relative rounded-2xl border border-border/80 bg-card/95 py-0 shadow-xl backdrop-blur-sm"
                >
                    <CardHeader
                        v-if="title || description"
                        class="px-6 pt-8 pb-0 text-center sm:px-10"
                    >
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-6 py-8 sm:px-10">
                        <slot />
                    </CardContent>
                </Card>
                <p class="text-center text-xs text-muted-foreground">
                    By continuing you agree to our
                    <Link
                        href="/terms"
                        class="underline underline-offset-4 hover:text-foreground"
                    >
                        Terms
                    </Link>
                    and
                    <Link
                        href="/privacy"
                        class="underline underline-offset-4 hover:text-foreground"
                    >
                        Privacy Policy
                    </Link>
                    .
                </p>
            </div>
        </div>
        <CookieConsentBanner />
    </div>
</template>
