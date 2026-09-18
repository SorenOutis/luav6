<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface SchoolBranding {
    name?: string;
    logoUrl?: string | null;
}

const page = usePage();
const branding = (page.props.schoolBranding ?? {}) as SchoolBranding;
</script>

<template>
    <div
        class="flex aspect-square size-7 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="branding.logoUrl"
            :src="branding.logoUrl"
            :alt="`${branding.name || 'School'} logo`"
            class="h-full w-full object-cover"
        />
        <AppLogoIcon
            v-else
            class="size-4 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            branding.name || 'LSIv6'
        }}</span>
    </div>
</template>
