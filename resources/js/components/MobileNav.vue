<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Home, ClipboardList, GraduationCap, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const page = usePage();

const navItems = computed(() =>
    [
        {
            label: 'Home',
            href: dashboard.url(),
            icon: Home,
            studentPageKey: 'dashboard',
        },
        {
            label: 'Assignments',
            href: '/assignments',
            icon: ClipboardList,
            studentPageKey: 'assignments',
        },
        {
            label: 'Exams',
            href: '/exams',
            icon: GraduationCap,
            studentPageKey: 'exams',
        },
        {
            label: 'Profile',
            href: edit.url(),
            icon: User,
            studentPageKey: 'profile',
        },
    ].filter(
        (item) =>
            page.props.studentPageControls?.pages?.[item.studentPageKey]
                ?.mode !== 'disabled',
    ),
);

const isActive = (href: string) => {
    // Current URL without query parameters
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
</script>

<template>
    <nav
        class="pb-safe-offset fixed right-0 bottom-0 left-0 z-50 flex h-20 items-center justify-around border-t border-sidebar-border bg-background/80 px-4 shadow-[0_-8px_30px_rgb(0,0,0,0.04)] backdrop-blur-2xl md:hidden"
    >
        <Link
            v-for="item in navItems"
            :key="item.label"
            :href="item.href"
            class="group relative flex flex-col items-center justify-center gap-1.5 py-2 transition-all duration-300"
            :class="
                isActive(item.href)
                    ? 'text-primary'
                    : 'text-muted-foreground/60 hover:text-foreground'
            "
        >
            <div
                class="flex items-center justify-center rounded-2xl transition-all duration-300"
                :class="
                    isActive(item.href)
                        ? '-mt-1 bg-primary/10 p-2.5 shadow-inner'
                        : 'p-2.5'
                "
            >
                <component
                    :is="item.icon"
                    :size="22"
                    :stroke-width="isActive(item.href) ? 2.5 : 2"
                    class="transition-transform duration-300"
                    :class="{ 'scale-110': isActive(item.href) }"
                />
            </div>
            <span
                class="text-[9px] font-black tracking-widest uppercase transition-all duration-300"
                :class="
                    isActive(item.href)
                        ? 'scale-105 opacity-100'
                        : 'opacity-60 group-hover:opacity-100'
                "
            >
                {{ item.label }}
            </span>

            <!-- Active Indicator Dot -->
            <div
                v-if="isActive(item.href)"
                class="animate-in fade-in zoom-in absolute bottom-0 h-1 w-1 rounded-full bg-primary shadow-lg shadow-primary/60 duration-500"
            />
        </Link>
    </nav>
</template>

<style scoped>
.pb-safe-offset {
    padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem);
}
</style>
