<script setup lang="ts">
import {
    Award,
    Zap,
    Flame,
    Trophy,
    TrendingUp,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import ClaimXpButton from '@/components/dashboard/ClaimXpButton.vue';

interface UserStats {
    totalXP: number;
    level: number;
    currentXP: number;
    maxXPForLevel: number;
    rank: string;
    achievements: number;
    points: number;
    streak: number;
    longestStreak: number;
}

interface StreakData {
    currentStreak: number;
    longestStreak: number;
}

interface ClaimXp {
    canClaim: boolean;
    amount: number;
    nextClaimAt: string | null;
}

interface Props {
    userStats: UserStats;
    streak?: StreakData;
    progressPercentage: number;
    claimXp?: ClaimXp;
}

const props = defineProps<Props>();

const hideClaimCard = ref(false);

// Track current XP values for animation on claim
const animLevel = useNumberAnimation(() => props.userStats.level);
const localTotalXp = ref(props.userStats.totalXP);
const animXP = useNumberAnimation(() => localTotalXp.value);
const animStreak = useNumberAnimation(() => props.streak?.currentStreak || 0);
const animPoints = useNumberAnimation(() => props.userStats.points);

// When totalXP changes from parent, sync local value
watch(
    () => props.userStats.totalXP,
    (newVal) => {
        localTotalXp.value = newVal;
    }
);

// When a new day's claim becomes available, unhide the card
watch(
    () => props.claimXp?.canClaim,
    (canClaim) => {
        if (canClaim) hideClaimCard.value = false;
    }
);

async function onClaimed(amount: number, _totalXp: number) {
    // Animate the XP counter up
    const startXp = localTotalXp.value;
    const endXp = startXp + amount;

    // Animate in steps for a satisfying counter effect
    const steps = Math.min(amount * 3, 20);
    const increment = amount / steps;
    for (let i = 1; i <= steps; i++) {
        await new Promise((r) => setTimeout(r, 30 + i * 8));
        localTotalXp.value = Math.round((startXp + increment * i) * 100) / 100;
    }
    localTotalXp.value = endXp;

    // Hide the claim card after the particle burst animation finishes
    setTimeout(() => {
        hideClaimCard.value = true;
    }, 1500);
}

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
        glowColor: 'blue' as const,
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
        glowColor: 'purple' as const,
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
        glowColor: 'orange' as const,
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
        glowColor: 'green' as const,
    },
]);
</script>

<template>
    <div class="flex flex-col gap-3 md:gap-4">
        <!-- Stats cards grid -->
        <div class="flex gap-3 overflow-x-auto scrollbar-none md:grid md:grid-cols-4 md:gap-4">
            <SpotlightCard
                v-for="(stat, idx) in displayStats"
                :key="stat.label"
                customSize
                :glowColor="stat.glowColor"
                :class="`stagger-${idx + 1}`"
                className="min-w-[155px] shrink-0 p-3 sm:p-5 group animate-fade-up bg-card/40 flex flex-col justify-between md:min-w-0 md:shrink"
            >
                <!-- Inner container to clip overflowing background icons without clipping the outer glow -->
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                >
                    <!-- Silhouette Background Icon -->
                    <div
                        class="pointer-events-none absolute -top-2 -right-2 opacity-[0.03] transition-all duration-700 group-hover:scale-110 group-hover:rotate-[20deg] group-hover:opacity-[0.06] sm:-top-3 sm:-right-3"
                    >
                        <component
                            :is="stat.icon"
                            class="h-16 w-16 sm:h-24 sm:w-24"
                        />
                    </div>
                </div>

                <div
                    class="relative z-10 flex h-full w-full flex-col justify-between"
                >
                    <div>
                        <div class="mb-3 flex items-center justify-between sm:mb-4">
                            <div
                                class="rounded-lg p-1.5 sm:rounded-xl sm:p-2"
                                :class="stat.bg"
                            >
                                <component
                                    :is="stat.icon"
                                    class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                    :class="stat.color"
                                />
                            </div>
                            <div
                                class="flex items-center gap-1 rounded-full border border-current/10 px-1.5 py-0.5 text-[7px] font-black tracking-wider uppercase sm:px-2 sm:text-[9px]"
                                :class="[stat.trendColor, stat.trendBg]"
                            >
                                <TrendingUp
                                    v-if="stat.trend.includes('+')"
                                    class="h-2 w-2 sm:h-2.5 sm:w-2.5"
                                />
                                <span>{{ stat.trend }}</span>
                            </div>
                        </div>

                        <div class="space-y-1 sm:space-y-1">
                            <p
                                class="text-[8px] leading-none font-black tracking-[0.15em] text-muted-foreground/50 uppercase sm:text-[10px] sm:tracking-[0.2em]"
                            >
                                {{ stat.label }}
                            </p>
                            <div class="flex items-baseline gap-1 sm:gap-1.5">
                                <h3
                                    class="premium-gradient-text text-xl leading-none font-black tracking-tighter tabular-nums sm:text-2xl lg:text-3xl"
                                >
                                    {{ stat.value }}
                                </h3>
                                <span
                                    class="text-[8px] font-black tracking-wider text-muted-foreground/30 uppercase sm:text-[10px]"
                                    >{{ stat.suffix }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Subtle Detail Bar -->
                    <div
                        class="mt-3 flex items-center justify-between border-t border-border/5 pt-2.5 sm:mt-4 sm:pt-3"
                    >
                        <span
                            class="text-[7px] font-black tracking-[0.05em] text-muted-foreground/40 uppercase sm:text-[9px] sm:tracking-[0.1em]"
                            >{{ stat.detail }}</span
                        >
                        <Sparkles
                            class="h-2.5 h-3 w-2.5 text-muted-foreground/20 transition-all duration-500 group-hover:scale-125 group-hover:rotate-12 group-hover:text-primary sm:w-3"
                        />
                    </div>
                </div>
            </SpotlightCard>
        </div>

        <!-- Claim XP Card -->
        <SpotlightCard
            v-if="claimXp && !hideClaimCard"
            customSize
            glowColor="yellow"
            className="p-3 sm:p-4 bg-card/40"
        >
            <ClaimXpButton
                :can-claim="claimXp.canClaim"
                :amount="claimXp.amount"
                :next-claim-at="claimXp.nextClaimAt"
                :streak="userStats.streak"
                @claimed="onClaimed"
            />
        </SpotlightCard>
    </div>
</template>
