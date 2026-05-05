<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Motion } from '@motionone/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import {
    Gamepad2, Shield, ChevronRight, Sparkles, Clock, Trophy,
    Zap, Flame, Target, Swords
} from 'lucide-vue-next';
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
    image?: string;
    category?: string;
    isBeta?: boolean;
}

defineProps<{ games: GameCard[] }>();

const breadcrumbs = [{ title: 'Games', href: '/games' }];

const upcoming = [
    { name: 'Word Sprint', desc: 'Type fast. Climb the leaderboard.', glowColor: 'orange' as const },
    { name: 'Logic Grid', desc: 'Daily deduction puzzles.', glowColor: 'purple' as const },
    { name: 'Code Duel', desc: 'Head-to-head algorithm battles.', glowColor: 'green' as const },
];

const gameGlowColors: ('orange' | 'green' | 'purple' | 'blue' | 'red' | 'emerald')[] = [
    'orange', 'green', 'purple', 'blue', 'red', 'emerald'
];

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const getGlowClasses = (color: string, type: 'top' | 'bottom') => {
    const map: Record<string, { top: string; bottom: string }> = {
        orange: { top: 'bg-orange-500/30', bottom: 'bg-orange-400/25' },
        green: { top: 'bg-emerald-500/30', bottom: 'bg-emerald-400/25' },
        purple: { top: 'bg-purple-500/30', bottom: 'bg-purple-400/25' },
        blue: { top: 'bg-blue-500/30', bottom: 'bg-blue-400/25' },
        red: { top: 'bg-red-500/30', bottom: 'bg-red-400/25' },
        emerald: { top: 'bg-teal-500/30', bottom: 'bg-teal-400/25' },
    };
    return map[color]?.[type] || '';
};

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
        <div ref="container" class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Decorative Orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Hero -->
            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.1 }"
                class="games-hero header-content relative group/hero flex flex-col md:flex-row md:items-end justify-between gap-6 z-10"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-[2px] bg-primary/40 rounded-full group-hover/hero:w-12 transition-all duration-500"></div>
                        <h1 class="text-2xl font-black tracking-tighter uppercase">Arcade_Node</h1>
                    </div>
                    <p class="text-muted-foreground text-sm font-medium pl-11 border-l-2 border-primary/10 group-hover/hero:border-primary/30 transition-colors uppercase tracking-widest text-[9px]">
                        Challenge your cognitive boundaries. Earn credits and boost your rank through competitive learning modules.
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/5 border border-primary/10 h-10 font-mono">
                        <Zap class="w-3.5 h-3.5 text-primary" />
                        <span class="text-[9px] font-black uppercase tracking-widest">ARCADE:ONLINE</span>
                    </div>
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
                        <SpotlightCard
                            customSize
                            :as="Link"
                            :href="game.href"
                            :glowColor="gameGlowColors[gIdx % gameGlowColors.length]"
                            :spotlightSize="350"
                            className="game-card relative group/game premium-hover bg-card/40 flex flex-col h-fit"
                            @mousemove="handleMouseMove"
                        >
                            <!-- Inner container to clip overflowing decorative elements without clipping the outer glow -->
                            <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                                <!-- Persistent colored corner highlights -->
                                <div
                                    class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-40 blur-3xl group-hover/game:opacity-70 transition-opacity duration-700"
                                    :class="getGlowClasses(gameGlowColors[gIdx % gameGlowColors.length], 'top')"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 w-56 h-56 rounded-full opacity-25 blur-3xl group-hover/game:opacity-50 transition-opacity duration-700"
                                    :class="getGlowClasses(gameGlowColors[gIdx % gameGlowColors.length], 'bottom')"
                                ></div>

                                <!-- Tech Grid Background -->
                                <div class="absolute inset-0 opacity-[0.03] group-hover/game:opacity-[0.05] transition-opacity">
                                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                                        <defs>
                                            <pattern :id="`game-grid-${gIdx}`" width="15" height="15" patternUnits="userSpaceOnUse">
                                                <path d="M 15 0 L 0 0 0 15" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                            </pattern>
                                        </defs>
                                        <rect width="100%" height="100%" :fill="`url(#game-grid-${gIdx})`" />
                                    </svg>
                                </div>

                                <!-- Tech Scanning Line -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/5 to-transparent w-32 h-full -translate-x-full group-hover/game:animate-scan-horizontal opacity-0 group-hover/game:opacity-100 transition-opacity"></div>

                                <!-- Hover Bloom Effect -->
                                <div class="absolute inset-0 opacity-0 group-hover/game:opacity-100 transition-opacity duration-700"
                                    :style="{ background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.08), transparent 40%)` }">
                                </div>

                                <!-- Corner Accents -->
                                <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-primary/20 opacity-0 group-hover/game:opacity-100 transition-opacity duration-500 rounded-tl-lg"></div>
                                <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-primary/20 opacity-0 group-hover/game:opacity-100 transition-opacity duration-500 rounded-br-lg"></div>
                            </div>

                            <div class="relative h-48 overflow-hidden border-b border-border/40">
                                <img :src="game.image" :alt="game.name" class="h-full w-full object-cover transition-transform duration-700 group-hover/game:scale-110" />
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

                            <div class="flex flex-col gap-4 p-5 relative z-10">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-black uppercase tracking-tight group-hover/game:text-primary transition-colors">{{ game.name }}</h3>
                                    <Sparkles class="h-4 w-4 text-primary opacity-0 transition-all duration-500 group-hover/game:opacity-100 group-hover/game:scale-110" />
                                </div>
                                <p class="text-xs leading-relaxed text-muted-foreground/70 line-clamp-2">{{ game.description }}</p>

                                <div class="flex items-center justify-between pt-4 border-t border-border/40">
                                    <div class="flex gap-4">
                                        <div v-for="stat in game.stats" :key="stat.label" class="flex flex-col gap-0.5">
                                            <span class="text-[8px] font-bold uppercase tracking-widest text-muted-foreground/50">{{ stat.label }}</span>
                                            <span class="text-[9px] font-black uppercase">{{ stat.value }}</span>
                                        </div>
                                    </div>
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover/game:bg-primary group-hover/game:text-primary-foreground">
                                        <ChevronRight class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </SpotlightCard>
                    </Motion>
                </div>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-6">
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.3 }"
                    >
                        <SpotlightCard
                            customSize
                            glowColor="orange"
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col"
                            @mousemove="handleMouseMove"
                        >
                            <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                                <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-40 blur-3xl bg-orange-500/30 group-hover/sidebar:opacity-70 transition-opacity duration-700"></div>
                                <div class="absolute -bottom-20 -left-20 w-56 h-56 rounded-full opacity-25 blur-3xl bg-orange-400/25 group-hover/sidebar:opacity-50 transition-opacity duration-700"></div>
                                <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-tl-lg"></div>
                                <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-br-lg"></div>
                                <div class="absolute inset-0 opacity-[0.03] group-hover/sidebar:opacity-[0.05] transition-opacity">
                                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                                        <defs>
                                            <pattern id="sidebar-grid-1" width="15" height="15" patternUnits="userSpaceOnUse">
                                                <path d="M 15 0 L 0 0 0 15" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                            </pattern>
                                        </defs>
                                        <rect width="100%" height="100%" fill="url(#sidebar-grid-1)" />
                                    </svg>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/5 to-transparent w-32 h-full -translate-x-full group-hover/sidebar:animate-scan-horizontal opacity-0 group-hover/sidebar:opacity-100 transition-opacity"></div>
                            </div>
                            <div class="relative z-10">
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
                            </div>
                        </SpotlightCard>
                    </Motion>

                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.4 }"
                    >
                        <SpotlightCard
                            customSize
                            glowColor="purple"
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col"
                            @mousemove="handleMouseMove"
                        >
                            <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                                <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-40 blur-3xl bg-purple-500/30 group-hover/sidebar:opacity-70 transition-opacity duration-700"></div>
                                <div class="absolute -bottom-20 -left-20 w-56 h-56 rounded-full opacity-25 blur-3xl bg-purple-400/25 group-hover/sidebar:opacity-50 transition-opacity duration-700"></div>
                                <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-tl-lg"></div>
                                <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-br-lg"></div>
                                <div class="absolute inset-0 opacity-[0.03] group-hover/sidebar:opacity-[0.05] transition-opacity">
                                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                                        <defs>
                                            <pattern id="sidebar-grid-2" width="15" height="15" patternUnits="userSpaceOnUse">
                                                <path d="M 15 0 L 0 0 0 15" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                            </pattern>
                                        </defs>
                                        <rect width="100%" height="100%" fill="url(#sidebar-grid-2)" />
                                    </svg>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/5 to-transparent w-32 h-full -translate-x-full group-hover/sidebar:animate-scan-horizontal opacity-0 group-hover/sidebar:opacity-100 transition-opacity"></div>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center gap-2 border-b border-border/40 px-5 py-3">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <h2 class="text-[10px] font-black uppercase tracking-[0.25em]">Upcoming</h2>
                                </div>
                                <ul class="flex flex-col">
                                    <li v-for="u in upcoming" :key="u.name" class="flex items-start gap-3 border-b border-border/40 p-4 last:border-0">
                                        <div class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="{
                                            'bg-orange-500/50': u.glowColor === 'orange',
                                            'bg-purple-500/50': u.glowColor === 'purple',
                                            'bg-emerald-500/50': u.glowColor === 'green',
                                        }" />
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-widest">{{ u.name }}</p>
                                            <p class="text-[10px] text-muted-foreground/80">{{ u.desc }}</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </SpotlightCard>
                    </Motion>

                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.5 }"
                    >
                        <SpotlightCard
                            customSize
                            glowColor="green"
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col p-5"
                            @mousemove="handleMouseMove"
                        >
                            <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                                <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-40 blur-3xl bg-emerald-500/30 group-hover/sidebar:opacity-70 transition-opacity duration-700"></div>
                                <div class="absolute -bottom-20 -left-20 w-56 h-56 rounded-full opacity-25 blur-3xl bg-emerald-400/25 group-hover/sidebar:opacity-50 transition-opacity duration-700"></div>
                                <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-tl-lg"></div>
                                <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-primary/20 opacity-0 group-hover/sidebar:opacity-100 transition-opacity duration-500 rounded-br-lg"></div>
                                <div class="absolute inset-0 opacity-[0.03] group-hover/sidebar:opacity-[0.05] transition-opacity">
                                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                                        <defs>
                                            <pattern id="sidebar-grid-3" width="15" height="15" patternUnits="userSpaceOnUse">
                                                <path d="M 15 0 L 0 0 0 15" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                            </pattern>
                                        </defs>
                                        <rect width="100%" height="100%" fill="url(#sidebar-grid-3)" />
                                    </svg>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/5 to-transparent w-32 h-full -translate-x-full group-hover/sidebar:animate-scan-horizontal opacity-0 group-hover/sidebar:opacity-100 transition-opacity"></div>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-[10px] font-black uppercase tracking-[0.2em]">Security Protocol</h2>
                                    <Shield class="h-4 w-4 text-primary" />
                                </div>
                                <p class="text-[10px] leading-relaxed text-muted-foreground/70 font-medium">All arcade progress is verified through the core assessment engine. Exploits will result in session termination.</p>
                            </div>
                        </SpotlightCard>
                    </Motion>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
