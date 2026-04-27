<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Gamepad2, Shield, ChevronRight, Sparkles, Clock, Trophy } from 'lucide-vue-next';

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
</script>

<template>
    <Head title="Games" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 xl:p-6">
            <!-- Hero -->
            <div class="surface-card relative mb-2 overflow-hidden">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent" />
                <div class="relative flex flex-wrap items-center justify-between gap-6 p-6 sm:p-8">
                    <div class="flex items-center gap-5">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5 shadow-inner">
                            <Gamepad2 class="h-8 w-8 text-primary" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
                                <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-primary/80">Arcade Experience</p>
                            </div>
                            <h1 class="text-3xl font-black uppercase tracking-tight sm:text-4xl">Games Hub</h1>
                            <p class="mt-1 text-sm text-muted-foreground/80">Pick a game, climb the leaderboard, earn stars.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-8 text-xs">
                        <div class="flex flex-col items-center gap-1">
                            <div class="flex items-center gap-2 text-primary">
                                <Sparkles class="h-4 w-4" />
                                <span class="font-black tabular-nums">{{ games.length }}</span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Available</span>
                        </div>
                        <div class="h-8 w-px bg-border/40" />
                        <div class="flex flex-col items-center gap-1">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span class="font-black tabular-nums">{{ upcoming.length }}</span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Coming Soon</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 items-start xl:grid-cols-[1fr_320px]">
                <!-- Games list -->
                <div class="grid gap-6 items-start sm:grid-cols-2">
                    <Link
                        v-for="game in games"
                        :key="game.slug"
                        :href="game.href"
                        class="surface-card premium-hover group flex flex-col h-fit"
                    >
                        <div class="relative flex items-center justify-between border-b border-border/40 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 shadow-sm">
                                    <Shield class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <h2 class="text-base font-black uppercase tracking-tight">{{ game.name }}</h2>
                                    <p class="text-[8px] font-bold uppercase tracking-[0.2em] text-primary/70">{{ game.tagline }}</p>
                                </div>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest shadow-sm border"
                                :class="game.status === 'live'
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                    : 'border-border bg-muted text-muted-foreground'"
                            >
                                {{ game.status === 'live' ? 'Live' : 'Soon' }}
                            </span>
                        </div>

                        <div class="relative flex flex-col p-4">
                            <p class="text-[13px] leading-snug text-muted-foreground/90">{{ game.description }}</p>

                            <div class="mt-3 flex flex-wrap gap-1">
                                <span
                                    v-for="tag in game.tags"
                                    :key="tag"
                                    class="rounded border border-border/40 bg-muted/30 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider text-muted-foreground"
                                >
                                    {{ tag }}
                                </span>
                            </div>

                            <!-- Stats Row - Ultra Compact -->
                            <div class="mt-4 flex items-center gap-5 overflow-x-auto pb-1 scrollbar-none">
                                <div
                                    v-for="s in game.stats"
                                    :key="s.label"
                                    class="flex shrink-0 flex-col gap-0"
                                >
                                    <p class="text-[7px] font-bold uppercase tracking-[0.1em] text-muted-foreground/60">{{ s.label }}</p>
                                    <p class="text-xs font-black tabular-nums">{{ s.value }}</p>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between rounded-lg bg-primary px-3 py-2 text-[10px] font-black uppercase tracking-[0.15em] text-primary-foreground shadow-md transition group-hover:bg-primary/90">
                                <span>Play Game</span>
                                <ChevronRight class="h-3.5 w-3.5" />
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-6">
                    <div class="surface-card">
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

                    <div class="surface-card">
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
                    </div>

                    <div class="surface-card p-5">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10">
                                <Sparkles class="h-4 w-4 text-primary" />
                            </div>
                            <h2 class="text-[10px] font-black uppercase tracking-[0.25em]">Pro Tip</h2>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-muted-foreground/80">
                            Earn stars by clearing levels at higher difficulty. Stars contribute to your seasonal rank.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
