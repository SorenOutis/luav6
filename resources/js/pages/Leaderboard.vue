<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Trophy } from 'lucide-vue-next';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import MobilePageHeader from '@/components/mobile/MobilePageHeader.vue';
import SpotlightCard from '@/components/ui/spotlight-card/SpotlightCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface LeaderboardUser {
    id: number;
    name: string;
    xp: number;
    avatar?: string;
    xpProgress: number;
    streak: number;
    joinedAt: string;
    weeklyXp: number;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser?: boolean;
    blurred?: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    /** Present for the super admin's platform-wide view: which workspace the section belongs to. */
    workspaceId?: number | null;
    workspaceName?: string | null;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface Season {
    id: number;
    name: string;
}

defineProps<{
    sectionLeaderboards: LeaderboardData[];
    activeSeasonName?: string;
    availableSeasons: Season[];
}>();
</script>

<template>
    <Head title="Leaderboard" />

    <AppLayout :breadcrumbs="[{ title: 'Leaderboard', href: '/leaderboard' }]">
        <div class="mobile-ui-page w-full space-y-6 p-4 sm:p-6 lg:p-8">
            <MobilePageHeader
                title="Leaderboard"
                subtitle="See how you rank in each section and track your XP progress."
                eyebrow="Compete and grow"
            />
            <div
                class="mobile-existing-header flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <Link
                        href="/dashboard"
                        class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition hover:text-foreground"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to dashboard
                    </Link>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-400/15 text-amber-400"
                        >
                            <Trophy class="h-5 w-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-black tracking-[0.2em] text-amber-400 uppercase"
                            >
                                Compete and grow
                            </p>
                            <h1
                                class="text-3xl font-black tracking-tight sm:text-4xl"
                            >
                                Leaderboard
                            </h1>
                        </div>
                    </div>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                    >
                        See how you rank in each section, track XP progress, and
                        learn from the students leading the season.
                    </p>
                </div>
            </div>

            <SpotlightCard
                customSize
                glowColor="yellow"
                className="bg-card/40 p-4 sm:p-6"
            >
                <ImprovedLeaderboard
                    :section-leaderboards="sectionLeaderboards"
                    :active-season-name="activeSeasonName"
                    :available-seasons="availableSeasons"
                />
            </SpotlightCard>
        </div>
    </AppLayout>
</template>
