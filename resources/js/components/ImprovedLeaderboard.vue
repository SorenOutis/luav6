<script setup lang="ts">
import { ref } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Trophy, Crown, TrendingUp, TrendingDown, Minus, Medal, Sparkles, User, Award } from 'lucide-vue-next';

interface LeaderboardUser {
    id: number;
    name: string;
    xp: number;
    avatar?: string;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser?: boolean;
}

interface Props {
    users: LeaderboardUser[];
}

const props = defineProps<Props>();

// Mock data extension for richer visuals
const top3 = props.users.slice(0, 3);
const theRest = props.users.slice(3);

const userRank = 14; // Mock rank for current user context
</script>

<template>
    <div class="space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 px-2">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Trophy class="w-4 h-4 text-primary" />
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary">Global Rankings</span>
                </div>
                <h2 class="text-3xl font-black tracking-tighter">Elite Assembly</h2>
                <p class="text-sm text-muted-foreground font-medium mt-1">
                    Competition is fierce. You are ranked <span class="text-foreground font-bold">#{{ userRank }}</span> out of 1,240.
                </p>
            </div>
            <button class="text-xs font-bold px-4 py-2 rounded-xl border border-border/40 bg-card hover:bg-muted transition-colors">
                View All Time
            </button>
        </div>

        <!-- Elite Top 3 Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(user, idx) in top3" :key="user.id"
                class="relative surface-card p-6 flex flex-col items-center text-center group transition-all duration-500 hover:-translate-y-2 animate-fade-up"
                :class="[
                    idx === 0 ? 'md:order-2 border-primary/20 scale-105 shadow-2xl shadow-primary/5' : (idx === 1 ? 'md:order-1' : 'md:order-3'),
                    `stagger-${idx + 1}`
                ]"
            >
                <!-- Rank Medal/Crown Overlay -->
                <div class="absolute -top-4 px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-[0.2em] shadow-xl"
                    :class="idx === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground border border-border/40'"
                >
                    Rank #{{ idx + 1 }}
                </div>

                <!-- Avatar Visual -->
                <div class="relative mb-6 mt-2">
                    <div class="absolute inset-0 rounded-full blur-2xl opacity-20 transition-opacity group-hover:opacity-40"
                        :class="idx === 0 ? 'bg-primary' : 'bg-muted-foreground'"
                    ></div>
                    <div class="relative w-24 h-24 rounded-full border-2 p-1 transition-transform duration-500 group-hover:scale-110"
                        :class="idx === 0 ? 'border-primary/50' : 'border-border/50'"
                    >
                        <div class="w-full h-full rounded-full bg-muted/30 flex items-center justify-center overflow-hidden">
                            <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                            <User v-else class="w-10 h-10 text-muted-foreground/40" />
                        </div>
                    </div>
                    <!-- Icon Overlay -->
                    <div v-if="idx === 0" class="absolute -bottom-1 -right-1 p-2 bg-primary rounded-xl shadow-lg animate-bounce">
                        <Crown class="w-4 h-4 text-primary-foreground" />
                    </div>
                </div>

                <div class="space-y-1 mb-6">
                    <h3 class="font-bold text-lg truncate w-full px-4">{{ user.name }}</h3>
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="text-xs font-bold text-primary">{{ user.xp.toLocaleString() }} XP</span>
                        <div class="w-1 h-1 rounded-full bg-muted-foreground/30"></div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Season 6</span>
                    </div>
                </div>

                <!-- Stats Bar -->
                <div class="w-full grid grid-cols-2 gap-2 border-t border-border/20 pt-4 mt-auto">
                    <div class="text-left">
                        <p class="text-[8px] font-bold uppercase text-muted-foreground">Win Rate</p>
                        <p class="text-xs font-black">94%</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[8px] font-bold uppercase text-muted-foreground">Streak</p>
                        <div class="flex items-center justify-end gap-1">
                            <Sparkles class="w-2.5 h-2.5 text-primary" />
                            <span class="text-xs font-black">12d</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- High Density List Rankings -->
        <div class="surface-card overflow-hidden">
            <div class="px-6 py-4 border-b border-border/20 bg-muted/5 flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Rankings #4 - #10</span>
                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-bold text-muted-foreground">Sort: <span class="text-foreground">Monthly</span></span>
                </div>
            </div>
            
            <div class="divide-y divide-border/10">
                <div v-for="(user, idx) in theRest" :key="user.id"
                    class="group px-6 py-4 flex items-center justify-between hover:bg-primary/[0.02] transition-colors cursor-default animate-fade-up"
                    :class="[`stagger-${idx + 4}`, { 'bg-primary/[0.03]': user.isCurrentUser }]"
                >
                    <div class="flex items-center gap-6">
                        <span class="text-sm font-black text-muted-foreground/40 w-6">#{{ idx + 4 }}</span>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-muted/30 border border-border/20 flex items-center justify-center overflow-hidden shrink-0 group-hover:scale-105 transition-transform">
                                <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                <User v-else class="w-5 h-5 text-muted-foreground/40" />
                            </div>
                            <div>
                                <h4 class="text-sm font-bold flex items-center gap-2">
                                    {{ user.name }}
                                    <span v-if="user.isCurrentUser" class="text-[8px] uppercase px-1.5 py-0.5 rounded bg-primary text-primary-foreground font-black">You</span>
                                </h4>
                                <div class="flex items-center gap-2 text-[10px] text-muted-foreground font-medium">
                                    <span>Joined Sept 2025</span>
                                    <div class="w-0.5 h-0.5 rounded-full bg-muted-foreground/30"></div>
                                    <span class="flex items-center gap-1">
                                        <Award class="w-3 h-3 text-primary" /> Silver III
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-8">
                        <!-- Trend indicator -->
                        <div class="hidden sm:flex flex-col items-center">
                            <component :is="user.trend === 'up' ? TrendingUp : (user.trend === 'down' ? TrendingDown : Minus)" 
                                class="w-4 h-4" 
                                :class="user.trend === 'up' ? 'text-emerald-500' : (user.trend === 'down' ? 'text-destructive' : 'text-muted-foreground')"
                            />
                            <span class="text-[8px] font-bold uppercase mt-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">Trend</span>
                        </div>

                        <!-- Data Column -->
                        <div class="text-right min-w-[100px]">
                            <p class="text-sm font-black tabular-nums">{{ user.xp.toLocaleString() }} <span class="text-[10px] font-bold text-muted-foreground">XP</span></p>
                            <p class="text-[9px] font-bold text-emerald-500">+1.2k weekly</p>
                        </div>

                        <!-- Action -->
                        <button class="p-2 rounded-xl text-muted-foreground hover:bg-primary/10 hover:text-primary transition-all opacity-0 group-hover:opacity-100">
                             <TrendingUp class="w-4 h-4 rotate-45" />
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-muted/5 text-center">
                <button class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors">
                    Load More Rankings
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom Noise Texture Overlay for Cards */
.surface-card::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.015;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
}
</style>
