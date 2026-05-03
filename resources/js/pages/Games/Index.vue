<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Motion } from '@motionone/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Gamepad2, Shield, ChevronRight, Sparkles, Clock, Trophy } from 'lucide-vue-next';
import { useLoader } from '@/composables/useLoader';

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);
const container = ref<HTMLElement | null>(null);

gsap.registerPlugin(ScrollTrigger);

interface GameStat { label: string; value: string }
interface GameCard {
    slug: string;
    name: string;
    tagline: string;
    description: string;
    status: 'live' | 'soon';
    href: string;
    accent: string;
    tags: string[];
    stats: GameStat[];
}

defineProps<{ games: GameCard[] }>();

const breadcrumbs = [{ title: 'Games', href: '/games' }];

const upcoming = [
    { name: 'Word Sprint', desc: 'Type fast. Climb the leaderboard.' },
    { name: 'Logic Grid', desc: 'Daily deduction puzzles.' },
    { name: 'Code Duel', desc: 'Head-to-head algorithm battles.' },
];

onMounted(() => {
    // Sync isBooted with global loader
    if (!isLoaderVisible.value) {
        isBooted.value = true;
    }

    watch(isLoaderVisible, (visible) => {
        if (!visible) {
            isBooted.value = true;
        }
    }, { immediate: true });
});
</script>

<template>
    <Head title="Games" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="container" class="flex flex-col gap-6 p-4 xl:p-6">
            <!-- Hero -->
            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.1 }"
                class="games-hero surface-card relative mb-2 overflow-hidden"
            >
                <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent opacity-50" />
                <div class="relative flex flex-col items-start gap-4 p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary border border-primary/20">
                            <Gamepad2 class="h-6 w-6" />
                        </div>
                        <h1 class="text-2xl font-black uppercase tracking-tighter">Arcade_Node</h1>
                    </div>
                    <p class="max-w-xl text-sm leading-relaxed text-muted-foreground/80 font-medium uppercase tracking-widest text-[10px]">
                        Challenge your cognitive boundaries. Earn credits and boost your rank through competitive learning modules.
                    </p>
                </div>
                <div class="absolute -right-8 -top-8 opacity-[0.03] scale-150 rotate-12">
                    <Gamepad2 class="h-48 w-48" />
                </div>
            </Motion>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Games List -->
                <div class="grid gap-6 items-start sm:grid-cols-2 lg:col-span-2">
                    <Motion
                        v-for="(game, gIdx) in games"
                        :key="game.slug"
                        :initial="{ opacity: 0, y: 40 }"
                        :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                        :in-view-options="{ once: true, margin: '-50px' }"
                        :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: gIdx * 0.1 }"
                        as="div"
                    >
                        <Link
                            :href="game.href"
                            class="game-card surface-card premium-hover group flex flex-col h-fit"
                        >
                            <div class="relative h-48 overflow-hidden border-b border-border/40">
                                <img :src="game.image" :alt="game.name" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-background/20 to-transparent" />
                                <div class="absolute bottom-4 left-4 flex items-center gap-2">
                                    <div class="rounded-md bg-background/80 backdrop-blur-md px-2 py-1 text-[8px] font-black uppercase tracking-widest border border-white/10">
                                        {{ game.category }}
                                    </div>
                                </div>
                                <div v-if="game.isBeta" class="absolute right-4 top-4 rounded-md bg-amber-500/20 backdrop-blur-md px-2 py-1 text-[8px] font-black uppercase tracking-widest text-amber-500 border border-amber-500/20">
                                    Experimental
                                </div>
                            </div>

                            <div class="flex flex-col gap-4 p-5">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-black uppercase tracking-tight group-hover:text-primary transition-colors">{{ game.name }}</h3>
                                    <Sparkles class="h-4 w-4 text-primary opacity-0 transition-all duration-500 group-hover:opacity-100 group-hover:scale-110" />
                                </div>
                                <p class="text-xs leading-relaxed text-muted-foreground/70 line-clamp-2">{{ game.description }}</p>
                                
                                <div class="flex items-center justify-between pt-4 border-t border-border/40">
                                    <div class="flex gap-4">
                                        <div v-for="stat in game.stats" :key="stat.label" class="flex flex-col gap-0.5">
                                            <span class="text-[8px] font-bold uppercase tracking-widest text-muted-foreground/50">{{ stat.label }}</span>
                                            <span class="text-[9px] font-black uppercase">{{ stat.value }}</span>
                                        </div>
                                    </div>
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-primary-foreground">
                                        <ChevronRight class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </Motion>
                </div>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-6">
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.3 }"
                        class="sidebar-card surface-card"
                    >
                        <div class="flex items-center gap-2 border-b border-border/40 px-5 py-3">
                            <Trophy class="h-4 w-4 text-primary" />
                            <h2 class="text-[10px] font-black uppercase tracking-[0.25em]">Featured Game</h2>
                        </div>
                        <div class="p-5">
                            <p class="text-sm font-black uppercase tracking-tight">Tower Defense</p>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground/80">Our flagship arcade experience — strategic tower placement across escalating difficulty tiers.</p>
                            <Link
                                href="/games/tower-defense"
                                class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-primary/5 border border-primary/10 py-2.5 text-[10px] font-black uppercase tracking-widest text-primary hover:bg-primary hover:text-primary-foreground transition-all duration-300"
                            >
                                Jump In <ChevronRight class="h-3 w-3" />
                            </Link>
                        </div>
                    </Motion>

                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.4 }"
                        class="sidebar-card surface-card"
                    >
                        <div class="flex items-center gap-2 border-b border-border/40 px-5 py-3">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                            <h2 class="text-[10px] font-black uppercase tracking-[0.25em]">Upcoming</h2>
                        </div>
                        <ul class="flex flex-col">
                            <li v-for="u in upcoming" :key="u.name" class="flex items-start gap-3 border-b border-border/40 p-4 last:border-0">
                                <div class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary/30" />
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-widest">{{ u.name }}</p>
                                    <p class="text-[10px] text-muted-foreground/80">{{ u.desc }}</p>
                                </div>
                            </li>
                        </ul>
                    </Motion>

                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.5 }"
                        class="sidebar-card surface-card p-5"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-[10px] font-black uppercase tracking-[0.2em]">Security Protocol</h2>
                            <Shield class="h-4 w-4 text-primary" />
                        </div>
                        <p class="text-[10px] leading-relaxed text-muted-foreground/70 font-medium">All arcade progress is verified through the core assessment engine. Exploits will result in session termination.</p>
                    </Motion>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
