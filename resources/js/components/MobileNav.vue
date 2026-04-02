<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Home, ClipboardList, GraduationCap, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const page = usePage();

const navItems = [
    { label: 'Home', href: dashboard.url(), icon: Home },
    { label: 'Assignments', href: '/assignments', icon: ClipboardList },
    { label: 'Exams', href: '/exams', icon: GraduationCap },
    { label: 'Profile', href: edit.url(), icon: User },
];

const isActive = (href: string) => {
    // Current URL without query parameters
    const currentPath = page.url.split('?')[0];
    
    if (href === '/' || href === dashboard.url()) {
        return currentPath === href || currentPath === '/dashboard' || currentPath === '/';
    }
    return currentPath.startsWith(href);
};
</script>

<template>
    <nav class="fixed bottom-0 left-0 right-0 z-50 flex h-16 items-center justify-around border-t border-sidebar-border bg-background/60 px-4 pb-safe backdrop-blur-xl md:hidden">
        <Link
            v-for="item in navItems"
            :key="item.label"
            :href="item.href"
            class="flex flex-col items-center justify-center gap-1.5 transition-all duration-300 relative group"
            :class="isActive(item.href) ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
        >
            <div 
                class="flex items-center justify-center rounded-full transition-all duration-300"
                :class="isActive(item.href) ? 'bg-primary/10 p-2 -mt-1' : 'p-2'"
            >
                <component 
                    :is="item.icon" 
                    :size="20"
                    :stroke-width="isActive(item.href) ? 2.5 : 2"
                    class="transition-transform duration-300"
                    :class="{ 'scale-110': isActive(item.href) }"
                />
            </div>
            <span 
                class="text-[10px] font-bold tracking-tight transition-all duration-300"
                :class="isActive(item.href) ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'"
            >
                {{ item.label }}
            </span>
            
            <!-- Active Indicator Dot -->
            <div 
                v-if="isActive(item.href)"
                class="absolute -bottom-1 h-1 w-1 rounded-full bg-primary animate-in fade-in zoom-in duration-300"
            />
        </Link>
    </nav>
</template>

<style scoped>
.pb-safe {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>
