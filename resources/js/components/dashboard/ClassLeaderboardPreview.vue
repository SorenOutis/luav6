<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Award, Crown, Medal, Sparkles, Trophy, User } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import EmptyState from './EmptyState.vue';

interface LeaderboardUser {
    id: number;
    name: string;
    avatar?: string;
    xp: number;
    level: number;
    xpProgress: number;
    streak: number;
    weeklyXp: number;
    isCurrentUser: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

const props = defineProps<{
    sectionLeaderboards: LeaderboardData[];
    activeSeasonName?: string | null;
}>();

const activeIndex = ref(0);
const STORAGE_KEY = 'dashboard_leaderboard_preview_section_id';

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    const savedIndex = props.sectionLeaderboards.findIndex((section) => section.sectionId === Number(saved));

    if (savedIndex >= 0) {
        activeIndex.value = savedIndex;
    }
});

watch(activeIndex, (index) => {
    const section = props.sectionLeaderboards[index];

    if (section) {
        localStorage.setItem(STORAGE_KEY, String(section.sectionId));
    }
});

const activeLeaderboard = computed(() => props.sectionLeaderboards[activeIndex.value] ?? null);
const topUsers = computed(() => activeLeaderboard.value?.users.slice(0, 5) ?? []);
const currentUser = computed(() => activeLeaderboard.value?.users.find((user) => user.isCurrentUser) ?? null);
const shouldShowCurrentUserFooter = computed(() => {
    if (!currentUser.value) return false;

    return !topUsers.value.some((user) => user.id === currentUser.value?.id);
});

const rankIcon = (rank: number) => {
    if (rank === 1) return Crown;
    if (rank === 2) return Medal;
    if (rank === 3) return Award;

    return Trophy;
};

const rankClass = (rank: number) => {
    if (rank === 1) return 'text-amber-400 bg-amber-400/10 border-amber-400/25';
    if (rank === 2) return 'text-slate-300 bg-slate-300/10 border-slate-300/20';
    if (rank === 3) return 'text-orange-400 bg-orange-400/10 border-orange-400/20';

    return 'text-muted-foreground bg-muted/30 border-border/30';
};
</script>

<template>
    <SpotlightCard customSize glowColor="blue" className="overflow-hidden p-4 sm:p-6 !bg-card/40" as="section">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between relative z-10">
            <div>
                <div class="mb-2 flex items-center gap-2">
                    <Trophy class="h-4 w-4 text-primary" />
                    <span class="text-[10px] font-black uppercase tracking-[0.22em] text-primary">
                        Class leaderboard
                    </span>
                </div>
                <h2 class="text-xl font-black tracking-tight sm:text-2xl">
                    {{ activeLeaderboard?.sectionName ?? 'Section rankings' }}
                </h2>
                <p v-if="activeLeaderboard" class="mt-1 text-xs text-muted-foreground">
                    You are <span class="font-black text-foreground">#{{ activeLeaderboard.userRank || '-' }}</span>
                    of {{ activeLeaderboard.totalPlayers }} classmates
                    <span v-if="activeSeasonName">in {{ activeSeasonName }}</span>
                </p>
            </div>

            <div
                v-if="sectionLeaderboards.length > 1"
                class="flex gap-2 overflow-x-auto pb-1 sm:max-w-[45%]"
            >
                <button
                    v-for="(section, index) in sectionLeaderboards"
                    :key="section.sectionId"
                    type="button"
                    :class="[
                        'shrink-0 rounded-xl border px-3 py-2 text-[10px] font-black uppercase tracking-widest transition-all',
                        activeIndex === index
                            ? 'border-primary/40 bg-primary text-primary-foreground shadow-lg shadow-primary/20'
                            : 'border-border/40 bg-card/40 text-muted-foreground hover:border-primary/30 hover:text-foreground',
                    ]"
                    @click="activeIndex = index"
                >
                    {{ section.sectionName }}
                </button>
            </div>
        </div>

        <EmptyState
            v-if="!activeLeaderboard || topUsers.length === 0"
            class="mt-5 relative z-10"
            compact
            :icon="Trophy"
            title="No rankings yet"
            message="Earn XP from exams, assignments, and activities to start the class leaderboard."
        />

        <div v-else class="mt-5 grid gap-3 lg:grid-cols-5 relative z-10">
            <SpotlightCard
                v-for="(user, index) in topUsers"
                :key="user.id"
                :as="Link"
                :href="`/u/${user.id}`"
                customSize
                glowColor="purple"
                :className="`group relative overflow-hidden rounded-2xl border p-3 transition-all duration-300 hover:-translate-y-0.5 flex flex-col justify-center ${user.isCurrentUser ? 'border-primary/40 ring-1 ring-primary/25 !bg-primary/[0.05] hover:!bg-primary/[0.1]' : 'border-border/35 !bg-card/30 hover:border-primary/40 hover:!bg-card/50'}`"
            >
                <div
                    v-if="user.isCurrentUser"
                    class="absolute -right-8 -top-8 h-20 w-20 rounded-full bg-primary/15 blur-2xl"
                    aria-hidden="true"
                />

                <div class="relative flex items-center gap-3 lg:flex-col lg:text-center z-10">
                    <div
                        :class="[
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border text-xs font-black lg:h-9 lg:w-9',
                            rankClass(index + 1),
                        ]"
                    >
                        <component :is="rankIcon(index + 1)" class="h-4 w-4" />
                    </div>

                    <div class="h-11 w-11 shrink-0 overflow-hidden rounded-xl border border-border/40 bg-muted/30 lg:h-14 lg:w-14">
                        <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <User class="h-5 w-5 text-muted-foreground/50" />
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 lg:w-full">
                        <div class="flex items-center gap-1.5 lg:justify-center">
                            <p class="truncate text-sm font-black tracking-tight group-hover:text-primary">
                                {{ user.name }}
                            </p>
                            <span
                                v-if="user.isCurrentUser"
                                class="rounded-full bg-primary px-1.5 py-0.5 text-[7px] font-black uppercase text-primary-foreground"
                            >
                                You
                            </span>
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[10px] font-bold text-muted-foreground lg:justify-center">
                            <span>{{ user.xp.toLocaleString() }} XP</span>
                            <span class="h-1 w-1 rounded-full bg-muted-foreground/30" />
                            <span>Lv {{ user.level }}</span>
                        </div>
                    </div>
                </div>
            </SpotlightCard>
        </div>

        <div
            v-if="shouldShowCurrentUserFooter && currentUser"
            class="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-primary/25 bg-primary/[0.06] p-3"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Sparkles class="h-4 w-4 shrink-0 text-primary" />
                <p class="truncate text-xs font-bold">
                    You are currently ranked #{{ activeLeaderboard?.userRank }} with {{ currentUser.xp.toLocaleString() }} XP.
                </p>
            </div>
            <span class="shrink-0 text-[10px] font-black uppercase tracking-widest text-primary">
                Keep climbing
            </span>
        </div>
    </SpotlightCard>
</template>
