<script setup lang="ts">
import { CalendarDays, History } from 'lucide-vue-next';
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

// Template refs so the header quick action can open the SAME modal the card
// itself opens on click (single source of truth for each modal).
const levelCardRef = ref<InstanceType<typeof LevelProgressCard> | null>(null);
const streakCardRef = ref<InstanceType<typeof StreakCard> | null>(null);

const panes = computed(() => [
    { key: 'xp' as PaneKey, label: 'XP & level', icon: TrendingUp },
    { key: 'streak' as PaneKey, label: 'Streak', icon: Flame },
    { key: 'season' as PaneKey, label: 'Season', icon: Zap },
]);

// One explicit affordance per pane so the modals are discoverable without
// hunting: the cards remain fully clickable too.
const quickAction = computed(() => {
    if (activePane.value === 'xp') {
        return {
            key: 'xp',
            label: 'XP history',
            icon: History,
            run: () => levelCardRef.value?.openHistory(),
        };
    }
    if (activePane.value === 'streak') {
        return {
            key: 'streak',
            label: 'Streak calendar',
            icon: CalendarDays,
            run: () => streakCardRef.value?.openCalendar(),
        };
    }
    return null; // Season has no modal.
});
</script>

<template>
    <section
        class="surface-card w-full min-w-0 p-3 sm:p-4"
        aria-label="Progress"
        data-tour="dashboard-progress"
    >
        <!-- Segmented control + pane quick action -->
        <div class="mb-3 flex items-center gap-2">
            <div
                class="flex flex-1 items-center gap-0.5 rounded-full border border-border/50 bg-background/60 p-1"
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

            <!-- Explicit modal entry point for the active pane -->
            <button
                v-if="quickAction"
                type="button"
                class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full border border-[#D97757]/30 bg-[#D97757]/[0.07] px-3 py-1.5 text-[12px] font-semibold text-[#D97757] transition-colors hover:bg-[#D97757]/15"
                :title="`Open ${quickAction.label}`"
                @click="quickAction.run()"
            >
                <component :is="quickAction.icon" class="h-3.5 w-3.5" />
                <span class="max-lg:hidden">{{ quickAction.label }}</span>
            </button>
        </div>

        <!-- Panes: v-show (not v-if) so the XP-history and streak-calendar
             modals inside stay mounted and keep their state while switching. -->
        <div v-show="activePane === 'xp'" role="tabpanel">
            <LevelProgressCard
                ref="levelCardRef"
                :user-stats="userStats"
                :breakdown="breakdown ?? []"
                :xp-history="xpHistory ?? []"
                :claim-xp="claimXp"
                :bonus-xp="bonusXp"
            />
        </div>

        <div v-show="activePane === 'streak'" role="tabpanel">
            <StreakCard
                ref="streakCardRef"
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
