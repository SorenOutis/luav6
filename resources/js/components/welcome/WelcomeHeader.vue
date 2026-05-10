<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Command, Sun, Moon } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { Motion } from '@motionone/vue';
import { computed } from 'vue';

const props = defineProps<{
    canRegister: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
    isBooted?: boolean;
    branding?: {
        name?: string;
        tagline?: string;
        logoUrl?: string | null;
        accentColor?: string;
    };
}>();

const emit = defineEmits(['magnetic', 'resetMagnetic']);

const { appearance, toggleTheme } = useAppearance();
const brandName = computed(() => props.branding?.name || 'LSI Engine');
const brandTagline = computed(() => props.branding?.tagline || 'Learning Systems Intelligence');

const handleMagnetic = (e: MouseEvent) => emit('magnetic', e);
const resetMagnetic = (e: MouseEvent) => emit('resetMagnetic', e);

const scrollToSection = (e: MouseEvent, targetId: string) => {
    e.preventDefault();
    if (targetId === 'top') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        history.pushState(null, '', window.location.pathname);
        return;
    }
    const el = document.getElementById(targetId);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.pushState(null, '', `#${targetId}`);
    }
};

const navItems = [
    { label: 'Home',         target: 'top' },
    { label: 'Stats',        target: 'metrics' },
    { label: 'How It Works', target: 'architecture' },
    { label: 'Features',     target: 'features' },
];
</script>

<template>
    <header class="sticky top-0 z-50 flex w-full items-center justify-between px-6 py-5 lg:px-16 lg:py-6 border-b border-border/10 dark:border-border/5 backdrop-blur-2xl bg-background/60 dark:bg-background/30 transition-colors duration-500">
        <!-- Header glow line -->
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
        
        <Motion 
            :initial="{ x: -20, opacity: 0 }"
            :animate="isBooted ? { x: 0, opacity: 1 } : {}"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.2 }"
            class="nav-item flex items-center gap-3 lg:gap-4 group cursor-pointer"
        >
            <div class="relative flex h-10 w-10 items-center justify-center overflow-hidden text-foreground transition-all duration-700 group-hover:rotate-[180deg]">
                <div class="absolute inset-0 rounded-xl bg-primary/5 dark:bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <img
                    v-if="branding?.logoUrl"
                    :src="branding.logoUrl"
                    :alt="`${brandName} logo`"
                    class="relative z-10 h-full w-full rounded-xl object-cover"
                />
                <Command v-else class="h-6 w-6 lg:h-7 lg:w-7 relative z-10" />
            </div>
            <div class="flex flex-col leading-none">
                <span class="max-w-[11rem] truncate text-[10px] lg:text-xs font-black tracking-[0.24em] uppercase">{{ brandName }}</span>
                <span class="max-w-[11rem] truncate text-[7px] lg:text-[8px] font-bold text-primary/60 uppercase mt-1 tracking-widest">{{ brandTagline }}</span>
            </div>
        </Motion>

        <Motion 
            :initial="{ y: -20, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : {}"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.3 }"
            class="hidden lg:flex absolute left-1/2 -translate-x-1/2 items-center gap-10"
        >
            <a
                v-for="item in navItems"
                :key="item.label"
                :href="`#${item.target}`"
                @click="(e) => scrollToSection(e, item.target)"
                @mousemove="handleMagnetic"
                @mouseleave="resetMagnetic"
                class="nav-item text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors relative group"
            >
                {{ item.label }}
                <span class="absolute -bottom-2 left-1/2 w-0 h-px bg-primary -translate-x-1/2 transition-all duration-300 group-hover:w-full"></span>
            </a>
            <Link
                href="/about"
                @mousemove="handleMagnetic"
                @mouseleave="resetMagnetic"
                class="nav-item text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors relative group"
            >
                About
                <span class="absolute -bottom-2 left-1/2 w-0 h-px bg-primary -translate-x-1/2 transition-all duration-300 group-hover:w-full"></span>
            </Link>
        </Motion>

        <Motion 
            :initial="{ y: -10, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : {}"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.4 }"
            as="nav" 
            class="flex items-center gap-4 lg:gap-8"
        >
            <!-- Theme Toggle Button - always visible -->
            <button 
                @click="toggleTheme" 
                class="relative p-2.5 text-muted-foreground hover:text-foreground transition-all active:scale-90 rounded-xl hover:bg-muted/40"
                aria-label="Toggle Theme"
            >
                <Sun v-if="appearance === 'dark'" class="h-4 w-4 lg:h-5 lg:w-5" />
                <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
            </button>

            <template v-if="auth.user">
                <Link :href="dashboard()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-muted-foreground hover:text-primary transition-all flex items-center gap-2"
                >
                    <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.6)]"></div>
                    <span class="hidden sm:inline">Access Engine</span>
                    <span class="sm:hidden">Engine</span>
                </Link>
            </template>
            <template v-else>
                <Link :href="login()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors"
                >
                    Login
                </Link>
                <Link v-if="canRegister" :href="register()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="nav-item relative bg-foreground text-background px-5 lg:px-8 py-2.5 lg:py-3 text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] hover:bg-primary transition-all shadow-2xl overflow-hidden group"
                >
                    <span class="relative z-10">Join</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary to-primary/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                </Link>
            </template>
        </Motion>
    </header>
</template>
