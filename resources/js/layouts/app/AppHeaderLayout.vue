<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import MobileNav from '@/components/MobileNav.vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <SeoHead noindex />
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent class="pb-[4.25rem] md:pb-0">
            <slot />
            <MobileNav />
        </AppContent>
    </AppShell>
</template>
