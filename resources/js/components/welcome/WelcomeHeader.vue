<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { Command, Sun, Moon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

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
const brandTagline = computed(
    () => props.branding?.tagline || 'Learning Systems Intelligence',
);

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
    { label: 'Home', target: 'top' },
    { label: 'Stats', target: 'metrics' },
    { label: 'How It Works', target: 'architecture' },
    { label: 'Features', target: 'features' },
];
</script>

<template>
    <header
        class="sticky top-0 z-50 flex w-full items-center justify-between border-b border-border/10 bg-background/60 px-6 py-5 backdrop-blur-2xl transition-colors duration-500 lg:px-16 lg:py-6 dark:border-border/5 dark:bg-background/30"
    >
        <!-- Header glow line -->
        <div
            class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"
        ></div>

        <Motion
            :initial="{ x: -20, opacity: 0 }"
            :animate="isBooted ? { x: 0, opacity: 1 } : { x: -20, opacity: 0 }"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.2 }"
            class="nav-item group flex cursor-pointer items-center gap-3 lg:gap-4"
        >
            <div
                class="relative flex h-10 w-10 items-center justify-center overflow-hidden text-foreground transition-all duration-700 group-hover:rotate-[180deg]"
            >
                <div
                    class="absolute inset-0 rounded-xl bg-primary/5 opacity-0 transition-opacity duration-500 group-hover:opacity-100 dark:bg-primary/10"
                ></div>
                <img
                    v-if="branding?.logoUrl"
                    :src="branding.logoUrl"
                    :alt="`${brandName} logo`"
                    class="relative z-10 h-full w-full rounded-xl object-cover"
                />
                <Command v-else class="relative z-10 h-6 w-6 lg:h-7 lg:w-7" />
            </div>
            <div class="flex flex-col leading-none">
                <span
                    class="max-w-[11rem] truncate text-[10px] font-black tracking-[0.24em] uppercase lg:text-xs"
                    >{{ brandName }}</span
                >
                <span
                    class="mt-1 max-w-[11rem] truncate text-[7px] font-bold tracking-widest text-primary/60 uppercase lg:text-[8px]"
                    >{{ brandTagline }}</span
                >
            </div>
        </Motion>

        <Motion
            :initial="{ y: -20, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : { y: -20, opacity: 0 }"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.3 }"
            class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-10 lg:flex"
        >
            <a
                v-for="item in navItems"
                :key="item.label"
                :href="`#${item.target}`"
                @click="(e) => scrollToSection(e, item.target)"
                @mousemove="handleMagnetic"
                @mouseleave="resetMagnetic"
                class="nav-item group relative text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-colors hover:text-foreground"
            >
                {{ item.label }}
                <span
                    class="absolute -bottom-2 left-1/2 h-px w-0 -translate-x-1/2 bg-primary transition-all duration-300 group-hover:w-full"
                ></span>
            </a>
            <Link
                href="/about"
                @mousemove="handleMagnetic"
                @mouseleave="resetMagnetic"
                class="nav-item group relative text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-colors hover:text-foreground"
            >
                About
                <span
                    class="absolute -bottom-2 left-1/2 h-px w-0 -translate-x-1/2 bg-primary transition-all duration-300 group-hover:w-full"
                ></span>
            </Link>
        </Motion>

        <Motion
            :initial="{ y: -10, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : { y: -10, opacity: 0 }"
            :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.4 }"
            as="nav"
            class="flex items-center gap-4 lg:gap-8"
        >
            <!-- Theme Toggle Button - always visible -->
            <button
                @click="toggleTheme"
                class="relative rounded-xl p-2.5 text-muted-foreground transition-all hover:bg-muted/40 hover:text-foreground active:scale-90"
                aria-label="Toggle Theme"
            >
                <Sun
                    v-if="appearance === 'dark'"
                    class="h-4 w-4 lg:h-5 lg:w-5"
                />
                <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
            </button>

            <template v-if="auth.user">
                <Link
                    :href="dashboard()"
                    @mousemove="handleMagnetic"
                    @mouseleave="resetMagnetic"
                    class="nav-item flex items-center gap-2 text-[9px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-all hover:text-primary lg:text-[10px] lg:tracking-[0.3em]"
                >
                    <div
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.6)]"
                    ></div>
                    <span class="hidden sm:inline">Access Engine</span>
                    <span class="sm:hidden">Engine</span>
                </Link>
            </template>
            <template v-else>
                <Link
                    :href="login()"
                    @mousemove="handleMagnetic"
                    @mouseleave="resetMagnetic"
                    class="nav-item text-[9px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-colors hover:text-foreground lg:text-[10px]"
                >
                    Login
                </Link>
                <Link
                    v-if="canRegister"
                    :href="register()"
                    @mousemove="handleMagnetic"
                    @mouseleave="resetMagnetic"
                    class="nav-item group relative overflow-hidden bg-foreground px-5 py-2.5 text-[9px] font-black tracking-[0.2em] text-background uppercase shadow-2xl transition-all hover:bg-primary lg:px-8 lg:py-3 lg:text-[10px]"
                >
                    <span class="relative z-10">Join</span>
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-primary to-primary/80 opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                    ></div>
                </Link>
            </template>
        </Motion>
    </header>
</template>
