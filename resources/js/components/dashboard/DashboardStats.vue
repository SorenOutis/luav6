<script setup lang="ts">
import { computed } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Award, Zap, Flame, Trophy, TrendingUp, Sparkles } from 'lucide-vue-next';

interface UserStats {
    totalXP: number;
    level: number;
    currentXP: number;
    maxXPForLevel: number;
    rank: string;
    achievements: number;
    points: number;
}

interface StreakData {
    currentStreak: number;
    longestStreak: number;
}

interface Props {
    userStats: UserStats;
    streak?: StreakData;
    progressPercentage: number;
}

const props = defineProps<Props>();

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

// Animated values
const animLevel = useNumberAnimation(() => props.userStats.level);
const animXP = useNumberAnimation(() => props.userStats.totalXP);
const animStreak = useNumberAnimation(() => props.streak?.currentStreak || 0);
const animPoints = useNumberAnimation(() => props.userStats.points);

const displayStats = computed(() => [
    { 
        label: 'Current Rank', 
        value: animLevel.value, 
        suffix: '',
        icon: Award, 
        color: 'text-foreground', 
        bg: 'bg-muted/50',
        detail: 'Level ' + props.userStats.level,
        trend: '+1',
        trendColor: 'text-primary',
        trendBg: 'bg-primary/10'
    },
    { 
        label: 'Total Exp', 
        value: animXP.value.toLocaleString(), 
        suffix: ' XP',
        icon: Zap, 
        color: 'text-foreground', 
        bg: 'bg-muted/50',
        detail: 'Season progress',
        trend: '+2.4k',
        trendColor: 'text-primary',
        trendBg: 'bg-primary/10'
    },
    { 
        label: 'Day Streak', 
        value: animStreak.value, 
        suffix: ' Days',
        icon: Flame, 
        color: 'text-foreground', 
        bg: 'bg-muted/50',
        detail: `Best: ${props.streak?.longestStreak || 0}`,
        trend: 'Active',
        trendColor: 'text-primary',
        trendBg: 'bg-primary/10'
    },
    { 
        label: 'Total Points', 
        value: animPoints.value.toLocaleString(), 
        suffix: ' Pts',
        icon: Trophy, 
        color: 'text-foreground', 
        bg: 'bg-muted/50',
        detail: 'Balance',
        trend: '+150',
        trendColor: 'text-primary',
        trendBg: 'bg-primary/10'
    }
]);
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4">
        <div v-for="(stat, idx) in displayStats" :key="stat.label"
            class="surface-card p-3 sm:p-5 premium-hover group animate-fade-up relative overflow-hidden backdrop-blur-md bg-card/40 border-border/40"
            :class="`stagger-${idx + 1}`"
            @mousemove="handleMouseMove"
        >
            <!-- Hover Bloom Effect -->
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.08), transparent 40%)` }">
            </div>

            <!-- Silhouette Background Icon -->
            <div class="absolute -right-2 -top-2 sm:-right-3 sm:-top-3 opacity-[0.03] group-hover:opacity-[0.06] transition-all duration-700 pointer-events-none group-hover:scale-110 group-hover:rotate-[20deg]">
                <component :is="stat.icon" class="w-16 h-16 sm:w-24 sm:h-24" />
            </div>

            <div class="relative z-10">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="p-1.5 sm:p-2 rounded-lg sm:rounded-xl" :class="stat.bg">
                        <component :is="stat.icon" class="w-3 h-3 sm:w-4 sm:h-4" :class="stat.color" />
                    </div>
                    <div class="flex items-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-full text-[7px] sm:text-[9px] font-black uppercase tracking-wider border border-current/10" :class="[stat.trendColor, stat.trendBg]">
                        <TrendingUp v-if="stat.trend.includes('+')" class="w-2 sm:w-2.5 h-2 sm:h-2.5" />
                        <span>{{ stat.trend }}</span>
                    </div>
                </div>

                <div class="space-y-0.5 sm:space-y-1">
                    <p class="text-[8px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-muted-foreground/50 leading-none">{{ stat.label }}</p>
                    <div class="flex items-baseline gap-1 sm:gap-1.5">
                        <h3 class="text-lg sm:text-2xl lg:text-3xl font-black tracking-tighter leading-none premium-gradient-text tabular-nums">
                            {{ stat.value }}
                        </h3>
                        <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-muted-foreground/30">{{ stat.suffix }}</span>
                    </div>
                </div>

                <!-- Subtle Detail Bar -->
                <div class="mt-3 sm:mt-4 pt-2 sm:pt-3 border-t border-border/5 flex items-center justify-between">
                    <span class="text-[7px] sm:text-[9px] font-black text-muted-foreground/40 tracking-[0.05em] sm:tracking-[0.1em] uppercase">{{ stat.detail }}</span>
                    <Sparkles class="w-2.5 h-2.5 sm:w-3 h-3 text-muted-foreground/20 group-hover:text-primary group-hover:scale-125 group-hover:rotate-12 transition-all duration-500" />
                </div>
            </div>
        </div>
    </div>
</template>
