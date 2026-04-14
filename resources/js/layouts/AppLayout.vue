<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
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
    <AppLayout :breadcrumbs="breadcrumbs" :hide-sidebar="hideSidebar">
        <slot />
    </AppLayout>
</template>
