<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Shield,
    ChevronRight,
    Sparkles,
    Clock,
    Trophy,
    Zap,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import PageSkeleton from '@/components/PageSkeleton.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);
const container = ref<HTMLElement | null>(null);

gsap.registerPlugin(ScrollTrigger);

interface GameStat {
    label: string;
    value: string;
}
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
    {
        name: 'Word Sprint',
        desc: 'Type fast. Climb the leaderboard.',
        glowColor: 'orange' as const,
    },
    {
        name: 'Logic Grid',
        desc: 'Daily deduction puzzles.',
        glowColor: 'purple' as const,
    },
    {
        name: 'Code Duel',
        desc: 'Head-to-head algorithm battles.',
        glowColor: 'green' as const,
    },
];

const gameGlowColors: (
    | 'orange'
    | 'green'
    | 'purple'
    | 'blue'
    | 'red'
    | 'emerald'
)[] = ['orange', 'green', 'purple', 'blue', 'red', 'emerald'];

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

    watch(
        isLoaderVisible,
        (visible) => {
            if (!visible) {
                isBooted.value = true;
            }
        },
        { immediate: true },
    );
});
</script>

<template>
    <Head title="Games" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Skeleton Loading State -->
        <template v-if="!isBooted">
            <div class="relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 perspective-[1000px] md:p-10">
                <PageSkeleton
                    :hero="true"
                    :subtitle="true"
                    :actions="1"
                    :count="0"
                    variant="minimal"
                    wrapperClass="z-10 mb-4"
                />
                <div class="z-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:col-span-2">
                        <div
                            v-for="i in 4"
                            :key="i"
                            class="flex flex-col overflow-hidden rounded-xl border border-border/10 bg-card/30"
                        >
                            <div class="h-44 animate-pulse bg-primary/10"></div>
                            <div class="flex flex-col gap-3 p-5">
                                <div class="h-4 w-3/4 animate-pulse rounded bg-primary/10"></div>
                                <div class="h-3 w-full animate-pulse rounded bg-primary/10"></div>
                                <div class="h-3 w-2/3 animate-pulse rounded bg-primary/10"></div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex gap-4">
                                        <div class="h-8 w-16 animate-pulse rounded bg-primary/10"></div>
                                        <div class="h-8 w-16 animate-pulse rounded bg-primary/10"></div>
                                    </div>
                                    <div class="h-8 w-8 animate-pulse rounded-lg bg-primary/10"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-6">
                        <div class="h-64 animate-pulse rounded-xl border border-border/10 bg-card/30"></div>
                        <div class="h-52 animate-pulse rounded-xl border border-border/10 bg-card/30"></div>
                        <div class="h-32 animate-pulse rounded-xl border border-border/10 bg-card/30"></div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Real Content -->
        <template v-if="isBooted">
            <div
                ref="container"
                class="relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 perspective-[1000px] md:p-10"
            >
            <!-- Decorative Orbs -->
            <div
                class="orb pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>
            <div
                class="orb pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>

            <!-- Hero -->
            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{
                    duration: 1,
                    ease: [0.16, 1, 0.3, 1],
                    delay: 0.1,
                }"
                class="games-hero header-content group/hero relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-end"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-[2px] w-8 rounded-full bg-primary/40 transition-all duration-500 group-hover/hero:w-12"
                        ></div>
                        <h1
                            class="text-2xl font-black tracking-tighter uppercase"
                        >
                            Arcade_Node
                        </h1>
                    </div>
                    <p
                        class="border-l-2 border-primary/10 pl-11 text-sm text-[9px] font-medium tracking-widest text-muted-foreground uppercase transition-colors group-hover/hero:border-primary/30"
                    >
                        Challenge your cognitive boundaries. Earn credits and
                        boost your rank through competitive learning modules.
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <div
                        class="flex h-10 items-center gap-2 rounded-full border border-primary/10 bg-primary/5 px-4 py-1.5 font-mono"
                    >
                        <Zap class="h-3.5 w-3.5 text-primary" />
                        <span
                            class="text-[9px] font-black tracking-widest uppercase"
                            >ARCADE:ONLINE</span
                        >
                    </div>
                </div>
            </Motion>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Games List -->
                <div
                    class="grid items-start gap-6 sm:grid-cols-2 lg:col-span-2"
                >
                    <Motion
                        v-for="(game, gIdx) in games"
                        :key="game.slug"
                        :initial="{ opacity: 0, y: 40 }"
                        :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                        :in-view-options="{ once: true, margin: '-50px' }"
                        :transition="{
                            duration: 1,
                            ease: [0.16, 1, 0.3, 1],
                            delay: gIdx * 0.1,
                        }"
                        as="div"
                    >
                        <SpotlightCard
                            customSize
                            :as="Link"
                            :href="game.href"
                            :glowColor="
                                gameGlowColors[gIdx % gameGlowColors.length]
                            "
                            :spotlightSize="350"
                            className="game-card relative group/game premium-hover bg-card/40 flex flex-col h-fit"
                            @mousemove="handleMouseMove"
                        >
                            <!-- Inner container to clip overflowing decorative elements without clipping the outer glow -->
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <!-- Persistent colored corner highlights -->
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-40 blur-3xl transition-opacity duration-700 group-hover/game:opacity-70"
                                    :class="
                                        getGlowClasses(
                                            gameGlowColors[
                                                gIdx % gameGlowColors.length
                                            ],
                                            'top',
                                        )
                                    "
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full opacity-25 blur-3xl transition-opacity duration-700 group-hover/game:opacity-50"
                                    :class="
                                        getGlowClasses(
                                            gameGlowColors[
                                                gIdx % gameGlowColors.length
                                            ],
                                            'bottom',
                                        )
                                    "
                                ></div>

                                <!-- Tech Grid Background -->
                                <div
                                    class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/game:opacity-[0.05]"
                                >
                                    <svg
                                        class="h-full w-full"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="100%"
                                        height="100%"
                                    >
                                        <defs>
                                            <pattern
                                                :id="`game-grid-${gIdx}`"
                                                width="15"
                                                height="15"
                                                patternUnits="userSpaceOnUse"
                                            >
                                                <path
                                                    d="M 15 0 L 0 0 0 15"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="0.5"
                                                />
                                            </pattern>
                                        </defs>
                                        <rect
                                            width="100%"
                                            height="100%"
                                            :fill="`url(#game-grid-${gIdx})`"
                                        />
                                    </svg>
                                </div>

                                <!-- Tech Scanning Line -->
                                <div
                                    class="group-hover/game:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/game:opacity-100"
                                ></div>

                                <!-- Hover Bloom Effect -->
                                <div
                                    class="absolute inset-0 opacity-0 transition-opacity duration-700 group-hover/game:opacity-100"
                                    :style="{
                                        background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.08), transparent 40%)`,
                                    }"
                                ></div>

                                <!-- Corner Accents -->
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/game:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/game:opacity-100"
                                ></div>
                            </div>

                            <div
                                class="relative h-48 overflow-hidden border-b border-border/40"
                            >
                                <img
                                    :src="game.image"
                                    :alt="game.name"
                                    class="h-full w-full object-cover transition-transform duration-700 group-hover/game:scale-110"
                                />
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-background/90 via-background/20 to-transparent"
                                />
                                <div
                                    class="absolute bottom-4 left-4 flex items-center gap-2"
                                >
                                    <div
                                        class="rounded-md border border-white/10 bg-background/80 px-2 py-1 text-[8px] font-black tracking-widest uppercase backdrop-blur-md"
                                    >
                                        {{ game.category }}
                                    </div>
                                </div>
                                <div
                                    v-if="game.isBeta"
                                    class="absolute top-4 right-4 rounded-md border border-amber-500/20 bg-amber-500/20 px-2 py-1 text-[8px] font-black tracking-widest text-amber-500 uppercase backdrop-blur-md"
                                >
                                    Experimental
                                </div>
                            </div>

                            <div class="relative z-10 flex flex-col gap-4 p-5">
                                <div class="flex items-center justify-between">
                                    <h3
                                        class="text-sm font-black tracking-tight uppercase transition-colors group-hover/game:text-primary"
                                    >
                                        {{ game.name }}
                                    </h3>
                                    <Sparkles
                                        class="h-4 w-4 text-primary opacity-0 transition-all duration-500 group-hover/game:scale-110 group-hover/game:opacity-100"
                                    />
                                </div>
                                <p
                                    class="line-clamp-2 text-xs leading-relaxed text-muted-foreground/70"
                                >
                                    {{ game.description }}
                                </p>

                                <div
                                    class="flex items-center justify-between border-t border-border/40 pt-4"
                                >
                                    <div class="flex gap-4">
                                        <div
                                            v-for="stat in game.stats"
                                            :key="stat.label"
                                            class="flex flex-col gap-0.5"
                                        >
                                            <span
                                                class="text-[8px] font-bold tracking-widest text-muted-foreground/50 uppercase"
                                                >{{ stat.label }}</span
                                            >
                                            <span
                                                class="text-[9px] font-black uppercase"
                                                >{{ stat.value }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover/game:bg-primary group-hover/game:text-primary-foreground"
                                    >
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
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-orange-500/30 opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-orange-400/25 opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/sidebar:opacity-[0.05]"
                                >
                                    <svg
                                        class="h-full w-full"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="100%"
                                        height="100%"
                                    >
                                        <defs>
                                            <pattern
                                                id="sidebar-grid-1"
                                                width="15"
                                                height="15"
                                                patternUnits="userSpaceOnUse"
                                            >
                                                <path
                                                    d="M 15 0 L 0 0 0 15"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="0.5"
                                                />
                                            </pattern>
                                        </defs>
                                        <rect
                                            width="100%"
                                            height="100%"
                                            fill="url(#sidebar-grid-1)"
                                        />
                                    </svg>
                                </div>
                                <div
                                    class="group-hover/sidebar:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10">
                                <div
                                    class="flex items-center gap-2 border-b border-border/40 px-5 py-3"
                                >
                                    <Trophy class="h-4 w-4 text-primary" />
                                    <h2
                                        class="text-[10px] font-black tracking-[0.25em] uppercase"
                                    >
                                        Featured Game
                                    </h2>
                                </div>
                                <div class="p-5">
                                    <p
                                        class="text-sm font-black tracking-tight uppercase"
                                    >
                                        Tower Defense
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground/80"
                                    >
                                        Our flagship arcade experience —
                                        strategic tower placement across
                                        escalating difficulty tiers.
                                    </p>
                                    <Link
                                        href="/games/tower-defense"
                                        class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-primary/10 bg-primary/5 py-2.5 text-[10px] font-black tracking-widest text-primary uppercase transition-all duration-300 hover:bg-primary hover:text-primary-foreground"
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
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-purple-500/30 opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-purple-400/25 opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/sidebar:opacity-[0.05]"
                                >
                                    <svg
                                        class="h-full w-full"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="100%"
                                        height="100%"
                                    >
                                        <defs>
                                            <pattern
                                                id="sidebar-grid-2"
                                                width="15"
                                                height="15"
                                                patternUnits="userSpaceOnUse"
                                            >
                                                <path
                                                    d="M 15 0 L 0 0 0 15"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="0.5"
                                                />
                                            </pattern>
                                        </defs>
                                        <rect
                                            width="100%"
                                            height="100%"
                                            fill="url(#sidebar-grid-2)"
                                        />
                                    </svg>
                                </div>
                                <div
                                    class="group-hover/sidebar:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10">
                                <div
                                    class="flex items-center gap-2 border-b border-border/40 px-5 py-3"
                                >
                                    <Clock
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <h2
                                        class="text-[10px] font-black tracking-[0.25em] uppercase"
                                    >
                                        Upcoming
                                    </h2>
                                </div>
                                <ul class="flex flex-col">
                                    <li
                                        v-for="u in upcoming"
                                        :key="u.name"
                                        class="flex items-start gap-3 border-b border-border/40 p-4 last:border-0"
                                    >
                                        <div
                                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                                            :class="{
                                                'bg-orange-500/50':
                                                    u.glowColor === 'orange',
                                                'bg-purple-500/50':
                                                    u.glowColor === 'purple',
                                                'bg-emerald-500/50':
                                                    u.glowColor === 'green',
                                            }"
                                        />
                                        <div>
                                            <p
                                                class="text-[11px] font-black tracking-widest uppercase"
                                            >
                                                {{ u.name }}
                                            </p>
                                            <p
                                                class="text-[10px] text-muted-foreground/80"
                                            >
                                                {{ u.desc }}
                                            </p>
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
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-emerald-500/30 opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-emerald-400/25 opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/sidebar:opacity-[0.05]"
                                >
                                    <svg
                                        class="h-full w-full"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="100%"
                                        height="100%"
                                    >
                                        <defs>
                                            <pattern
                                                id="sidebar-grid-3"
                                                width="15"
                                                height="15"
                                                patternUnits="userSpaceOnUse"
                                            >
                                                <path
                                                    d="M 15 0 L 0 0 0 15"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="0.5"
                                                />
                                            </pattern>
                                        </defs>
                                        <rect
                                            width="100%"
                                            height="100%"
                                            fill="url(#sidebar-grid-3)"
                                        />
                                    </svg>
                                </div>
                                <div
                                    class="group-hover/sidebar:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10">
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <h2
                                        class="text-[10px] font-black tracking-[0.2em] uppercase"
                                    >
                                        Security Protocol
                                    </h2>
                                    <Shield class="h-4 w-4 text-primary" />
                                </div>
                                <p
                                    class="text-[10px] leading-relaxed font-medium text-muted-foreground/70"
                                >
                                    All arcade progress is verified through the
                                    core assessment engine. Exploits will result
                                    in session termination.
                                </p>
                            </div>
                        </SpotlightCard>
                    </Motion>
                </aside>
            </div>
        </div>
        </template>
    </AppLayout>
</template>
