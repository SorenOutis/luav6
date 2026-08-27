<script setup lang="ts">
import DailyRewardCard from '@/components/dashboard/DailyRewardCard.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';
import SeasonProgressBand from '@/components/dashboard/SeasonProgressBand.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';
import TodayStrip from '@/components/dashboard/TodayStrip.vue';
import type { NextUpItem } from '@/components/dashboard/TodayStrip.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';

interface Announcement {
    id: number;
    title: string;
    description: string;
    link?: string;
    sectionName?: string | null;
    createdAt?: string | null;
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

interface Season {
    id: number;
    name: string;
    startDate?: string | null;
    endDate?: string | null;
}

interface LeaderboardUser {
    id: number;
    name: string;
    avatar?: string;
    xp: number;
    level: number;
    xpProgress: number;
    streak: number;
    joinedAt: string;
    weeklyXp: number;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    workspaceId?: number | null;
    workspaceName?: string | null;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface ClaimXp {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    baseXp?: number;
    nextClaimAt: string | null;
    lastClaimedAt?: string | null;
    showPrompt?: boolean;
}

withDefaults(
    defineProps<{
        userName: string;
        userAvatar?: string;
        profileHref?: string;
        userStats: UserStats;
        announcements: Announcement[];
        timeBasedGreeting: string;
        greetingTheme: string;
        statusColor: string;
        smarterStatus: string;
        isRefreshing: boolean;
        dueTodayCount: number;
        overdueCount: number;
        upcoming24hCount: number;
        nextItem: NextUpItem | null;
        claimXp: ClaimXp;
        bonusXp?: ClaimXp;
        statsBreakdown?: {
            xp: { label: string; amount: number; count: number }[];
            points: { label: string; amount: number; count: number }[];
        };
        xpHistory?: {
            id: number;
            reason: string;
            description: string | null;
            amount: number;
            createdAt: string;
            isClaim: boolean;
        }[];
        loginDates?: string[];
        sectionLeaderboards: LeaderboardData[];
        activeSeason?: Season | null;
        availableSeasons?: Season[];
        primaryLeaderboard?: {
            sectionName: string;
            totalPlayers: number;
            userRank: number;
        } | null;
        leaderboardExpanded: boolean;
    }>(),
    {
        userAvatar: undefined,
        profileHref: undefined,
        bonusXp: undefined,
        statsBreakdown: undefined,
        xpHistory: undefined,
        loginDates: () => [],
        activeSeason: null,
        availableSeasons: () => [],
        primaryLeaderboard: null,
    },
);

const emit = defineEmits<{
    closeAnnouncement: [id: number];
    refresh: [];
    openSectionModal: [];
    claimed: [];
    toggleLeaderboard: [];
}>();
</script>

<template>
    <div class="mobile-dashboard-composition md:hidden">
        <DashboardHero
            class="dashboard-hero"
            data-tour="dashboard-hero"
            :user-name="userName"
            :user-avatar="userAvatar"
            :profile-href="profileHref"
            :user-stats="userStats"
            :announcements="announcements"
            :time-based-greeting="timeBasedGreeting"
            :greeting-theme="greetingTheme"
            :status-color="statusColor"
            :smarter-status="smarterStatus"
            :is-refreshing="isRefreshing"
            @close-announcement="emit('closeAnnouncement', $event)"
            @refresh="emit('refresh')"
            @open-section-modal="emit('openSectionModal')"
        />

        <TodayStrip
            class="dashboard-focus"
            data-tour="dashboard-today"
            :due-today-count="dueTodayCount"
            :overdue-count="overdueCount"
            :upcoming-24h-count="upcoming24hCount"
            :next-item="nextItem"
        />

        <DailyRewardCard
            class="dashboard-reward"
            data-tour="dashboard-daily-reward"
            :claim-xp="claimXp"
            :streak="userStats.streak"
            @claimed="emit('claimed')"
        />

        <section class="mobile-dashboard-progress" aria-label="Your progress">
            <LevelProgressCard
                class="dashboard-progress-level"
                data-tour="dashboard-level-card"
                :user-stats="userStats"
                :breakdown="statsBreakdown?.xp ?? []"
                :xp-history="xpHistory ?? []"
                :claim-xp="claimXp"
                :bonus-xp="bonusXp"
            />
            <div class="mobile-dashboard-mini-grid">
                <StreakCard
                    data-tour="dashboard-streak-card"
                    :current-streak="userStats.streak"
                    :longest-streak="userStats.longestStreak"
                    :login-dates="loginDates ?? []"
                />
                <SeasonProgressBand
                    data-tour="dashboard-season"
                    :name="activeSeason?.name ?? null"
                    :start-date="activeSeason?.startDate ?? null"
                    :end-date="activeSeason?.endDate ?? null"
                />
            </div>
        </section>

        <section
            class="mobile-dashboard-leaderboard"
            data-tour="dashboard-leaderboard"
        >
            <button
                type="button"
                class="mobile-dashboard-section-trigger"
                :aria-expanded="leaderboardExpanded"
                aria-controls="mobile-dashboard-leaderboard-panel"
                @click="emit('toggleLeaderboard')"
            >
                <span>
                    <strong>Leaderboard</strong>
                    <small v-if="primaryLeaderboard">
                        {{ primaryLeaderboard.sectionName }} ·
                        {{ primaryLeaderboard.totalPlayers }} students
                    </small>
                </span>
                <span class="mobile-dashboard-section-trigger__rank">
                    <template v-if="primaryLeaderboard">
                        #{{ primaryLeaderboard.userRank }}
                    </template>
                    <span aria-hidden="true">›</span>
                </span>
            </button>
            <div
                v-show="leaderboardExpanded"
                id="mobile-dashboard-leaderboard-panel"
                class="mobile-dashboard-leaderboard__panel"
            >
                <ImprovedLeaderboard
                    class="dashboard-leaderboard"
                    :section-leaderboards="sectionLeaderboards"
                    :active-season-name="activeSeason?.name"
                    :available-seasons="availableSeasons ?? []"
                    show-view-button
                />
            </div>
        </section>

        <section
            class="mobile-dashboard-activity"
            aria-label="Activity"
            data-tour="dashboard-activity"
        >
            <div>
                <h2>Activity</h2>
                <p>Your last 4 weeks at a glance.</p>
            </div>
            <StreakHeatmap :login-dates="loginDates ?? []" />
        </section>
    </div>
</template>
