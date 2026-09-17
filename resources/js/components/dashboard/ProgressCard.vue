<script setup lang="ts">
import { Flame, TrendingUp, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';
import SeasonProgressBand from '@/components/dashboard/SeasonProgressBand.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';

interface ClaimXp {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    baseXp?: number;
    nextClaimAt: string | null;
    showPrompt?: boolean;
}

interface BonusXp {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    nextClaimAt: string | null;
    lastClaimedAt?: string | null;
}

interface UserStats {
    totalXP: number;
    level: number;
    currentXP: number;
    maxXPForLevel: number;
    rank: string;
    rankNumber: number;
    totalPlayers: number;
    achievements: number;
    points: number;
    streak: number;
    longestStreak: number;
    joinedAt: string;
}

interface StreakRestoreInfo {
    enabled: boolean;
    limit: number;
    used: number;
    remaining: number;
    costs: number[];
    nextCost: number;
    restoredDates: string[];
    resetsAt: string | null;
}

interface Props {
    userStats: UserStats;
    breakdown?: { label: string; amount: number; count: number }[];
    xpHistory?: {
        id: number;
        reason: string;
        description: string | null;
        amount: number;
        createdAt: string;
        isClaim: boolean;
    }[];
    claimXp?: ClaimXp;
    bonusXp?: BonusXp;
    loginDates?: string[];
    streakRestore?: StreakRestoreInfo | null;
    seasonName?: string | null;
    seasonStartDate?: string | null;
    seasonEndDate?: string | null;
}

defineProps<Props>();

type PaneKey = 'xp' | 'streak' | 'season';
const activePane = ref<PaneKey>('xp');

const panes = computed(() => [
    { key: 'xp' as PaneKey, label: 'XP & level', icon: TrendingUp },
    { key: 'streak' as PaneKey, label: 'Streak', icon: Flame },
    { key: 'season' as PaneKey, label: 'Season', icon: Zap },
]);
</script>

<template>
    <section
        class="surface-card w-full min-w-0 p-3 sm:p-4"
        aria-label="Progress"
        data-tour="dashboard-progress"
    >
        <!-- Segmented control -->
        <div
            class="mb-3 flex w-full items-center gap-0.5 rounded-full border border-border/50 bg-background/60 p-1"
            role="tablist"
            aria-label="Progress sections"
        >
            <button
                v-for="pane in panes"
                :key="pane.key"
                type="button"
                role="tab"
                :aria-selected="activePane === pane.key"
                class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-full px-2 py-1.5 text-[13px] font-semibold transition-colors"
                :class="
                    activePane === pane.key
                        ? 'bg-[#D97757] text-white'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activePane = pane.key"
            >
                <component :is="pane.icon" class="h-3.5 w-3.5" />
                <span>{{ pane.label }}</span>
            </button>
        </div>

        <!-- Panes: v-show (not v-if) so the XP-history and streak-calendar
             modals inside stay mounted and keep their state while switching. -->
        <div v-show="activePane === 'xp'" role="tabpanel">
            <LevelProgressCard
                :user-stats="userStats"
                :breakdown="breakdown ?? []"
                :xp-history="xpHistory ?? []"
                :claim-xp="claimXp"
                :bonus-xp="bonusXp"
            />
        </div>

        <div v-show="activePane === 'streak'" role="tabpanel">
            <StreakCard
                :current-streak="userStats.streak"
                :longest-streak="userStats.longestStreak"
                :login-dates="loginDates ?? []"
                :user-xp="userStats.totalXP"
                :restore="streakRestore ?? null"
            />
        </div>

        <div v-show="activePane === 'season'" role="tabpanel">
            <SeasonProgressBand
                :name="seasonName ?? null"
                :start-date="seasonStartDate ?? null"
                :end-date="seasonEndDate ?? null"
            />
        </div>
    </section>
</template>
