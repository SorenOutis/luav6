<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileNav from '@/components/MobileNav.vue';
import FloatingWidget from '@/components/FloatingWidget.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    hideSidebar?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    hideSidebar: false,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar v-if="!hideSidebar" />
        <AppContent variant="sidebar" :class="['overflow-x-hidden pb-20 md:pb-0', hideSidebar ? 'w-full ml-0' : '']">
            <AppSidebarHeader v-if="!hideSidebar" :breadcrumbs="breadcrumbs" />
            <slot />
            <MobileNav v-if="!hideSidebar" />
            <FloatingWidget />
        </AppContent>
    </AppShell>
</template>
