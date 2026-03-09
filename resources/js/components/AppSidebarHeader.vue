<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { appearance, updateAppearance } = useAppearance();

const toggleTheme = (event: MouseEvent) => {
    const newTheme = appearance.value === 'dark' ? 'light' : 'dark';
    
    if (!document.startViewTransition) {
        updateAppearance(newTheme);
        return;
    }

    const x = event.clientX;
    const y = event.clientY;
    const endRadius = Math.hypot(
        Math.max(x, innerWidth - x),
        Math.max(y, innerHeight - y)
    );

    const transition = document.startViewTransition(() => {
        updateAppearance(newTheme);
    });

    transition.ready.then(() => {
        const clipPath = [
            `circle(0px at ${x}px ${y}px)`,
            `circle(${endRadius}px at ${x}px ${y}px)`,
        ];
        
        document.documentElement.animate(
            {
                clipPath: newTheme === 'dark' ? [...clipPath].reverse() : clipPath,
            },
            {
                duration: 500,
                easing: 'ease-in-out',
                pseudoElement: newTheme === 'dark'
                    ? '::view-transition-old(root)'
                    : '::view-transition-new(root)',
            }
        );
    });
};

const currentTime = ref('');
const currentDate = ref('');
let timer: ReturnType<typeof setInterval>;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    currentDate.value = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};

onMounted(() => {
    updateTime();
    timer = setInterval(updateTime, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <div class="flex flex-col items-end justify-center">
                <span class="text-[10px] sm:text-xs font-bold text-foreground font-mono tracking-tight">{{ currentTime }}</span>
                <span class="text-[8px] sm:text-[10px] font-semibold text-muted-foreground uppercase tracking-wider font-sans">{{ currentDate }}</span>
            </div>
            
            <button
                @click="toggleTheme"
                class="inline-flex items-center justify-center rounded-md p-1.5 sm:p-2 text-sm font-medium transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                :aria-label="`Switch to ${appearance === 'dark' ? 'light' : 'dark'} mode`"
            >
                <Sun v-if="appearance === 'dark'" class="h-4 w-4 sm:h-5 sm:w-5" />
                <Moon v-else class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>
        </div>
    </header>
</template>
