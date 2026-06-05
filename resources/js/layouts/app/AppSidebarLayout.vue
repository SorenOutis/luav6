<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileNav from '@/components/MobileNav.vue';
import FloatingWidget from '@/components/FloatingWidget.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    hideSidebar?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    hideSidebar: false,
});

const page = usePage();
const currentStudentPage = computed(
    () => page.props.studentPageControls?.current,
);
const isBlurred = computed(() => currentStudentPage.value?.mode === 'blurred');
const blurMessage = computed(
    () =>
        currentStudentPage.value?.message ||
        'This page is temporarily blurred by your teacher.',
);
const contentClass = computed(() =>
    ['overflow-x-hidden pb-20 md:pb-0', props.hideSidebar ? 'w-full ml-0' : '']
        .filter(Boolean)
        .join(' '),
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar v-if="!props.hideSidebar" />
        <AppContent variant="sidebar" :class="contentClass">
            <AppSidebarHeader
                v-if="!props.hideSidebar"
                :breadcrumbs="props.breadcrumbs"
            />
            <div class="relative min-h-0 flex-1">
                <div
                    :class="{
                        'pointer-events-none blur-md select-none': isBlurred,
                    }"
                >
                    <slot />
                </div>

                <div
                    v-if="isBlurred"
                    class="absolute inset-0 z-40 flex items-center justify-center bg-background/35 px-4 backdrop-blur-sm"
                >
                    <section
                        class="w-full max-w-md rounded-lg border border-border bg-card/95 p-6 text-center shadow-lg"
                    >
                        <p
                            class="text-xs font-bold tracking-[0.25em] text-primary uppercase"
                        >
                            Page Blurred
                        </p>
                        <h2 class="mt-3 text-xl font-bold tracking-tight">
                            {{ currentStudentPage?.label }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ blurMessage }}
                        </p>
                    </section>
                </div>
            </div>
            <MobileNav v-if="!props.hideSidebar" />
            <FloatingWidget />
        </AppContent>
    </AppShell>
</template>
