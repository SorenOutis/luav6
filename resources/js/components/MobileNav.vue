<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useTimeoutFn } from '@vueuse/core';
import { gsap } from 'gsap';
import {
    LayoutGrid,
    GraduationCap,
    Award,
    MessageSquareText,
} from 'lucide-vue-next';
import { computed, ref, onMounted, watch, nextTick } from 'vue';
import { dashboard, grades } from '@/routes';
import { index as chatsIndex } from '@/routes/chats';
import { index as examsIndex } from '@/routes/exams';

const page = usePage();

const navItems = computed(() =>
    [
        {
            label: 'Home',
            href: dashboard.url(),
            icon: LayoutGrid,
            studentPageKey: 'dashboard',
        },
        {
            label: 'Exams',
            href: examsIndex().url,
            icon: GraduationCap,
            studentPageKey: 'exams',
        },
        {
            label: 'Grades',
            href: grades().url,
            icon: Award,
            studentPageKey: 'grades',
        },
        {
            label: 'Chats',
            href: chatsIndex().url,
            icon: MessageSquareText,
            studentPageKey: 'chats',
        },
    ].filter(
        (item) =>
            page.props.studentPageControls?.pages?.[item.studentPageKey]
                ?.mode !== 'disabled',
    ),
);

// --- Active Route Detection ---
const isActive = (href: string) => {
    const currentPath = page.url.split('?')[0];

    if (href === '/' || href === dashboard.url()) {
        return (
            currentPath === href ||
            currentPath === '/dashboard' ||
            currentPath === '/'
        );
    }
    return currentPath.startsWith(href);
};

const activeIndex = computed(() => {
    return navItems.value.findIndex((item) => isActive(item.href));
});

// --- GSAP Animated Indicator ---
const indicatorRef = ref<HTMLElement | null>(null);
const itemRefs = ref<(HTMLElement | null)[]>([]);

function animateIndicator() {
    if (
        activeIndex.value !== -1 &&
        activeIndex.value < navItems.value.length &&
        itemRefs.value[activeIndex.value] &&
        indicatorRef.value &&
        typeof (itemRefs.value[activeIndex.value] as any)
            .getBoundingClientRect === 'function'
    ) {
        const target = itemRefs.value[activeIndex.value]!;
        const targetRect = target.getBoundingClientRect();
        const parentRect = target.parentElement?.getBoundingClientRect() ?? {
            left: 0,
        };

        gsap.to(indicatorRef.value, {
            x: targetRect.left - parentRect.left + targetRect.width / 2 - 16,
            opacity: 1,
            width: 32,
            duration: 0.6,
            ease: 'power4.out',
        });
    } else if (indicatorRef.value) {
        gsap.to(indicatorRef.value, {
            opacity: 0,
            duration: 0.3,
        });
    }
}

function setItemRef(el: any, index: number) {
    itemRefs.value[index] = el as HTMLElement | null;
}

onMounted(() => {
    // Small delay to let DOM paint. useTimeoutFn cancels on unmount — a bare
    // setTimeout would fire into a destroyed component after navigation.
    useTimeoutFn(animateIndicator, 150);
});

watch(activeIndex, () => {
    nextTick(animateIndicator);
});
</script>

<template>
    <!-- Bottom Navigation Bar -->
    <nav
        aria-label="Mobile navigation"
        class="fixed right-0 bottom-0 left-0 z-50 mx-auto w-full md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)"
    >
        <div
            class="relative mx-2 mb-1.5 flex h-14 items-center justify-around overflow-hidden rounded-2xl border border-border/60 bg-background/85 px-2 shadow-2xl shadow-black/10 backdrop-blur-3xl dark:bg-zinc-950/90 dark:shadow-black/30"
        >
            <!-- Sliding Active Indicator -->
            <div
                ref="indicatorRef"
                class="pointer-events-none absolute top-1 z-0 h-1 w-8 rounded-full bg-primary opacity-0"
            />

            <Link
                v-for="(item, index) in navItems"
                :key="item.label"
                :href="item.href"
                :ref="(el) => setItemRef(el, index)"
                prefetch="click"
                cache-for="30s"
                class="relative z-10 flex flex-1 flex-col items-center justify-center gap-0.5 py-1.5 transition-all outline-none active:scale-90"
            >
                <div
                    class="flex items-center justify-center transition-all duration-500"
                    :class="[activeIndex === index ? 'scale-110' : '']"
                >
                    <component
                        :is="item.icon"
                        class="transition-all duration-500"
                        :class="
                            activeIndex === index
                                ? 'size-5 stroke-[2.5px] text-primary'
                                : 'size-5 stroke-[1.5px] text-muted-foreground'
                        "
                    />
                </div>

                <span
                    class="text-[10px] font-semibold tracking-wide uppercase transition-all duration-500"
                    :class="[
                        activeIndex === index
                            ? 'text-primary opacity-100'
                            : 'text-muted-foreground opacity-70',
                    ]"
                >
                    {{ item.label }}
                </span>
            </Link>
        </div>
    </nav>
</template>

<style scoped>
/* Ensure the actual padding from env() takes effect */
nav {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
