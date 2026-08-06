<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
}>();

interface SchoolBranding {
    name?: string;
    logoUrl?: string | null;
}

const page = usePage();
const branding = (page.props.schoolBranding ?? {}) as SchoolBranding;
</script>

<template>
    <div
        class="theme-neutral-page flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="group flex flex-col items-center gap-2.5 self-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border bg-background p-1.5 shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:border-primary/30"
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
                <Card class="rounded-xl py-0">
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
            </div>
        </div>
    </div>
</template>
