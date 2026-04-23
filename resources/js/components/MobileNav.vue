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
    <nav class="fixed bottom-0 left-0 right-0 z-50 flex h-20 items-center justify-around border-t border-sidebar-border bg-background/80 px-4 pb-safe-offset backdrop-blur-2xl md:hidden shadow-[0_-8px_30px_rgb(0,0,0,0.04)]">
        <Link
            v-for="item in navItems"
            :key="item.label"
            :href="item.href"
            class="flex flex-col items-center justify-center gap-1.5 transition-all duration-300 relative group py-2"
            :class="isActive(item.href) ? 'text-primary' : 'text-muted-foreground/60 hover:text-foreground'"
        >
            <div 
                class="flex items-center justify-center rounded-2xl transition-all duration-300"
                :class="isActive(item.href) ? 'bg-primary/10 p-2.5 -mt-1 shadow-inner' : 'p-2.5'"
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
                class="text-[9px] font-black uppercase tracking-widest transition-all duration-300"
                :class="isActive(item.href) ? 'opacity-100 scale-105' : 'opacity-60 group-hover:opacity-100'"
            >
                {{ item.label }}
            </span>
            
            <!-- Active Indicator Dot -->
            <div 
                v-if="isActive(item.href)"
                class="absolute bottom-0 h-1 w-1 rounded-full bg-primary animate-in fade-in zoom-in duration-500 shadow-[0_0_8px_rgba(var(--primary),0.6)]"
            />
        </Link>
    </nav>
</template>

<style scoped>
.pb-safe-offset {
    padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem);
}
</style>
