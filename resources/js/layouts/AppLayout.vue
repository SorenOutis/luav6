<script setup lang="ts">
import { useSlots } from 'vue';
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

const slots = useSlots();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :hide-sidebar="hideSidebar">
        <template v-if="slots['chat-navigation']" #chat-navigation>
            <slot name="chat-navigation" />
        </template>
        <slot />
    </AppLayout>
</template>
