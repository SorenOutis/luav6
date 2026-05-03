<script setup lang="ts">
import { computed } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Award, Zap, Flame, Trophy, TrendingUp, Sparkles } from 'lucide-vue-next';
import { SpotlightCard } from '@/components/ui/spotlight-card';

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
        trendBg: 'bg-primary/10',
        glowColor: 'blue' as const
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
        trendBg: 'bg-primary/10',
        glowColor: 'purple' as const
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
        trendBg: 'bg-primary/10',
        glowColor: 'orange' as const
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
        trendBg: 'bg-primary/10',
        glowColor: 'green' as const
    }
]);
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <SpotlightCard v-for="(stat, idx) in displayStats" :key="stat.label"
            customSize
            :glowColor="stat.glowColor"
            :class="`stagger-${idx + 1}`"
            className="p-4 sm:p-5 group animate-fade-up bg-card/40 flex flex-col justify-between"
        >
            <!-- Silhouette Background Icon -->
            <div class="absolute -right-2 -top-2 sm:-right-3 sm:-top-3 opacity-[0.03] group-hover:opacity-[0.06] transition-all duration-700 pointer-events-none group-hover:scale-110 group-hover:rotate-[20deg]">
                <component :is="stat.icon" class="w-16 h-16 sm:w-24 sm:h-24" />
            </div>

            <div class="relative z-10 w-full h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="p-1.5 sm:p-2 rounded-lg sm:rounded-xl" :class="stat.bg">
                            <component :is="stat.icon" class="w-3.5 h-3.5 sm:w-4 sm:h-4" :class="stat.color" />
                        </div>
                        <div class="flex items-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-full text-[7px] sm:text-[9px] font-black uppercase tracking-wider border border-current/10" :class="[stat.trendColor, stat.trendBg]">
                            <TrendingUp v-if="stat.trend.includes('+')" class="w-2 sm:w-2.5 h-2 sm:h-2.5" />
                            <span>{{ stat.trend }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 sm:space-y-1">
                        <p class="text-[8px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-muted-foreground/50 leading-none">{{ stat.label }}</p>
                        <div class="flex items-baseline gap-1 sm:gap-1.5">
                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter leading-none premium-gradient-text tabular-nums">
                                {{ stat.value }}
                            </h3>
                            <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-muted-foreground/30">{{ stat.suffix }}</span>
                        </div>
                    </div>
                </div>

                <!-- Subtle Detail Bar -->
                <div class="mt-3 sm:mt-4 pt-2.5 sm:pt-3 border-t border-border/5 flex items-center justify-between">
                    <span class="text-[7px] sm:text-[9px] font-black text-muted-foreground/40 tracking-[0.05em] sm:tracking-[0.1em] uppercase">{{ stat.detail }}</span>
                    <Sparkles class="w-2.5 h-2.5 sm:w-3 h-3 text-muted-foreground/20 group-hover:text-primary group-hover:scale-125 group-hover:rotate-12 transition-all duration-500" />
                </div>
            </div>
        </SpotlightCard>
    </div>
</template>
