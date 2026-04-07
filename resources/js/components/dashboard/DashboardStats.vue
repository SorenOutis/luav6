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
        color: 'text-blue-500', 
        bg: 'bg-blue-500/10',
        detail: 'Level ' + props.userStats.level
    },
    { 
        label: 'Total Exp', 
        value: animXP.value.toLocaleString(), 
        suffix: ' XP',
        icon: Zap, 
        color: 'text-amber-500', 
        bg: 'bg-amber-500/10',
        detail: 'Season progress'
    },
    { 
        label: 'Day Streak', 
        value: animStreak.value, 
        suffix: ' Days',
        icon: Flame, 
        color: 'text-orange-500', 
        bg: 'bg-orange-500/10',
        detail: `Personal best: ${props.streak?.longestStreak || 0}`
    },
    { 
        label: 'Total Points', 
        value: animPoints.value.toLocaleString(), 
        suffix: ' Pts',
        icon: Trophy, 
        color: 'text-purple-500', 
        bg: 'bg-purple-500/10',
        detail: 'Available to spend'
    }
]);
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div v-for="(stat, idx) in displayStats" :key="stat.label"
            class="surface-card p-4 sm:p-5 premium-hover group animate-fade-up relative overflow-hidden"
            :class="`stagger-${idx + 1}`"
            @mousemove="handleMouseMove"
        >
            <!-- Hover Bloom Effect -->
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), ${stat.bg.replace('/10', '/30')}, transparent 40%)` }">
            </div>

            <!-- Silhouette Background Icon - Moved to Upper Right -->
            <div class="absolute -right-3 -top-3 opacity-[0.03] group-hover:opacity-[0.06] transition-opacity duration-700 pointer-events-none">
                <component :is="stat.icon" class="w-20 h-20 rotate-12" />
            </div>

            <div class="relative z-10">
                <div class="flex items-start mb-4">
                    <!-- Showing only the trending badge on the left -->
                    <div class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 text-[10px] font-bold border border-emerald-500/10">
                        <TrendingUp class="w-3 h-3" />
                        <span>+12%</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60 leading-none">{{ stat.label }}</p>
                    <div class="flex items-baseline gap-1.5">
                        <h3 class="text-2xl sm:text-3xl font-black tracking-tighter leading-none">
                            {{ stat.value }}
                        </h3>
                        <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground/40">{{ stat.suffix }}</span>
                    </div>
                </div>

                <!-- Subtle Detail Bar -->
                <div class="mt-4 pt-4 border-t border-border/10 flex items-center justify-between">
                    <span class="text-[10px] font-bold text-muted-foreground/50 tracking-wide uppercase">{{ stat.detail }}</span>
                    <Sparkles class="w-3 h-3 text-muted-foreground/30 group-hover:text-primary group-hover:scale-110 transition-all duration-500" />
                </div>
            </div>
        </div>
    </div>
</template>
