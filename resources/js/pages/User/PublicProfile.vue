<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Calendar,
    Camera,
    Flame,
    LayoutGrid,
    Lock,
    Medal,
    Pencil,
    Share2,
    Shield,
    Sparkles,
    Trophy,
    UserCheck,
    UserPlus,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

interface Course {
    id: number;
    name: string;
    progress: number;
    completedLessons: number;
    totalLessons: number;
    xpEarned: number;
}

interface Badge {
    id: number;
    name: string;
    description: string;
    image?: string | null;
    iconUrl?: string | null;
    requiredLevel?: number | null;
    earned?: boolean;
    earnedSeason?: string | null;
    earnedAt?: string | null;
}

interface SocialUser {
    id: number;
    name: string;
    avatar: string | null;
}

interface RecentKudo {
    id: number;
    name: string;
    avatar: string | null;
    type: 'great-work' | 'on-fire' | 'keep-going';
    date: string | null;
}

interface SectionRank {
    id: number;
    name: string;
    rank: number;
    total: number;
}

interface HistoryItem {
    id: number;
    amount_xp: number;
    amount_points: number;
    reason: string;
    description: string;
    date: string;
    full_date: string;
    section: string | null;
}

const props = defineProps<{
    profileUser: {
        id: number;
        name: string;
        avatar: string | null;
        cover_photo: string | null;
        bio: string | null;
        sections: string[];
        streak: number;
        joinedAt: string;
        isCurrentUser: boolean;
    };
    stats: {
        level: number;
        xp: number;
        xpProgress?: number;
        rank: number;
        totalPlayers: number;
        badgesCount: number;
        followersCount: number;
        followingCount: number;
    };
    badges: Badge[];
    sectionRanks?: SectionRank[];
    courses: Course[];
    history: HistoryItem[];
    isSameSection: boolean;
    isFollowing: boolean;
    kudos: Record<'great-work' | 'on-fire' | 'keep-going', number>;
    viewerKudo: 'great-work' | 'on-fire' | 'keep-going' | null;
    recentKudos?: RecentKudo[];
    followers?: SocialUser[];
    following?: SocialUser[];
}>();

const { getInitials } = useInitials();

const breadcrumbItems = [
    { title: 'Dashboard', href: dashboard() },
    { title: props.profileUser.name, href: `/u/${props.profileUser.id}` },
];

const formatDelta = (value: number) => (value >= 0 ? `+${value}` : `${value}`);

const followPending = ref(false);
const toggleFollow = () => {
    if (followPending.value) return;

    followPending.value = true;
    const options = {
        preserveScroll: true,
        onFinish: () => (followPending.value = false),
    };

    if (props.isFollowing) {
        router.delete(`/u/${props.profileUser.id}/follow`, options);
    } else {
        router.post(`/u/${props.profileUser.id}/follow`, {}, options);
    }
};

const kudoOptions = [
    { key: 'great-work', label: '🎉 Great work' },
    { key: 'on-fire', label: '🔥 On fire' },
    { key: 'keep-going', label: '💪 Keep going' },
] as const;

const kudoPending = ref(false);
const sendKudo = (type: 'great-work' | 'on-fire' | 'keep-going') => {
    if (kudoPending.value || props.viewerKudo === type) return;

    kudoPending.value = true;
    router.post(
        `/u/${props.profileUser.id}/kudos`,
        { type },
        {
            preserveScroll: true,
            onFinish: () => (kudoPending.value = false),
        },
    );
};

const formatCount = (value: number) =>
    new Intl.NumberFormat('en-US', { notation: 'compact' }).format(value);

/** @username-style handle derived from the display name. */
const handle = computed(() => {
    const slug = props.profileUser.name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '')
        .slice(0, 18);

    return `@${slug || 'student'}`;
});

const countStats = computed(() => [
    { key: 'level', label: 'Level', value: props.stats.level },
    { key: 'followers', label: 'Followers', value: props.stats.followersCount },
    { key: 'following', label: 'Following', value: props.stats.followingCount },
    { key: 'badges', label: 'Badges', value: props.stats.badgesCount },
]);

// XP progress toward the next level, used to draw the ring around the avatar.
const levelProgress = computed(() => {
    const xp = props.stats.xp || 0;
    const inLevel = xp % 100;
    return Math.min(100, Math.max(0, (inLevel / 100) * 100));
});
const ringStyle = computed(() => ({
    background: `conic-gradient(#D97757 ${levelProgress.value}%, rgba(217,119,87,0.18) ${levelProgress.value}%)`,
}));

const earnedBadgesCount = computed(
    () => props.badges.filter((b) => b.earned).length,
);

const kudoLabel: Record<string, string> = {
    'great-work': '🎉 Great work',
    'on-fire': '🔥 On fire',
    'keep-going': '💪 Keep going',
};

// Followers / Following modals
const activeSocialList = ref<'followers' | 'following' | null>(null);
const socialModalOpen = computed(() => activeSocialList.value !== null);
const socialListTitle = computed(() =>
    activeSocialList.value === 'followers'
        ? 'Followers'
        : activeSocialList.value === 'following'
          ? 'Following'
          : '',
);
const socialListItems = computed<SocialUser[]>(() =>
    activeSocialList.value === 'followers'
        ? (props.followers ?? [])
        : activeSocialList.value === 'following'
          ? (props.following ?? [])
          : [],
);
const openSocialList = (which: 'followers' | 'following') => {
    activeSocialList.value = which;
};

// ── Tabs ────────────────────────────────────────────────────────────
type TabKey = 'activity' | 'achievements' | 'courses';

const tabs = computed(() => {
    const items: Array<{ key: TabKey; label: string; count: number }> = [
        { key: 'activity', label: 'Activity', count: props.history.length },
        {
            key: 'achievements',
            label: 'Achievements',
            count: earnedBadgesCount.value,
        },
    ];

    if (props.isSameSection) {
        items.push({
            key: 'courses',
            label: 'Courses',
            count: props.courses.length,
        });
    }

    return items;
});

const activeTab = ref<TabKey>('activity');

// ── Share ───────────────────────────────────────────────────────────
const shareLabel = ref('Share');

const shareProfile = async () => {
    const url = `${window.location.origin}/u/${props.profileUser.id}`;

    try {
        if (navigator.share) {
            await navigator.share({
                title: `${props.profileUser.name} on Lua`,
                url,
            });
            return;
        }

        await navigator.clipboard.writeText(url);
        shareLabel.value = 'Link copied';
        setTimeout(() => (shareLabel.value = 'Share'), 2000);
    } catch {
        // User dismissed the share sheet or the clipboard was unavailable.
    }
};

/** Icon paired with each history reason so the feed reads at a glance. */
const iconForReason = (reason: string) => {
    const value = reason.toLowerCase();

    if (value.includes('badge') || value.includes('achievement')) return Medal;
    if (value.includes('streak')) return Flame;
    if (value.includes('lesson') || value.includes('course')) return BookOpen;
    if (value.includes('exam') || value.includes('quiz')) return Trophy;

    return Sparkles;
};
</script>

<template>
    <Head :title="`${profileUser.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="profile-ui min-h-full bg-background pb-16">
            <!-- ════════════ Cover ════════════ -->
            <div class="relative">
                <div
                    class="relative w-full overflow-hidden bg-gradient-to-br from-muted via-muted/60 to-background sm:rounded-b-[2rem]"
                    style="aspect-ratio: 3"
                >
                    <!-- Decorative banner: alt="" so screen readers skip it. -->
                    <img
                        v-if="profileUser.cover_photo"
                        :src="profileUser.cover_photo"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />

                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center text-muted-foreground/50"
                    >
                        <Camera class="h-8 w-8" />
                    </div>

                    <!-- Legibility scrim -->
                    <div
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/25 to-transparent"
                        aria-hidden="true"
                    ></div>
                </div>

                <!-- ════════════ Identity row ════════════ -->
                <div class="w-full px-4 sm:px-6 lg:px-8 2xl:px-12">
                    <div
                        class="-mt-12 flex flex-col gap-4 sm:-mt-16 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="flex items-end gap-4">
                            <!-- XP progress ring around the (larger) avatar -->
                            <div
                                class="relative shrink-0 rounded-full p-[5px] sm:p-[6px]"
                                :style="ringStyle"
                                :title="`${Math.round(levelProgress)}% to the next level`"
                            >
                                <div class="rounded-full bg-background p-[3px]">
                                    <Avatar
                                        class="size-28 shrink-0 bg-muted shadow-lg sm:size-36"
                                    >
                                        <AvatarImage
                                            v-if="profileUser.avatar"
                                            :src="profileUser.avatar"
                                            :alt="profileUser.name"
                                            class="object-cover"
                                        />
                                        <AvatarFallback
                                            class="bg-muted text-3xl font-semibold text-foreground sm:text-4xl"
                                        >
                                            {{ getInitials(profileUser.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>
                            </div>

                            <div class="min-w-0 pb-1">
                                <div
                                    class="hidden items-center gap-2 sm:flex sm:pb-1"
                                >
                                    <span
                                        class="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Rank #{{ stats.rank }} of
                                        {{ stats.totalPlayers }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2 pb-1">
                            <Link
                                v-if="profileUser.isCurrentUser"
                                :href="editProfile()"
                                class="profile-btn inline-flex items-center gap-1.5 border border-border/60 bg-card px-4 text-[14px] text-foreground transition-colors hover:bg-muted"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Edit profile
                            </Link>
                            <button
                                v-if="
                                    !profileUser.isCurrentUser && isSameSection
                                "
                                type="button"
                                class="profile-btn inline-flex items-center gap-1.5 px-4 text-[14px] transition-colors"
                                :class="
                                    isFollowing
                                        ? 'border border-border/60 bg-card text-foreground hover:bg-muted'
                                        : 'bg-foreground text-background hover:bg-foreground/90'
                                "
                                :disabled="followPending"
                                @click="toggleFollow"
                            >
                                <UserCheck
                                    v-if="isFollowing"
                                    class="h-3.5 w-3.5"
                                />
                                <UserPlus v-else class="h-3.5 w-3.5" />
                                {{
                                    followPending
                                        ? 'Saving...'
                                        : isFollowing
                                          ? 'Following'
                                          : 'Follow'
                                }}
                            </button>
                            <button
                                type="button"
                                class="profile-btn inline-flex items-center gap-1.5 border border-border/60 bg-card px-4 text-[14px] text-foreground transition-colors hover:bg-muted"
                                @click="shareProfile"
                            >
                                <Share2 class="h-3.5 w-3.5" />
                                {{ shareLabel }}
                            </button>
                        </div>
                    </div>

                    <!-- Name block -->
                    <div class="mt-3 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="profile-title text-[26px] leading-tight text-foreground sm:text-[34px]"
                            >
                                {{ profileUser.name }}
                            </h1>
                            <span
                                v-if="profileUser.isCurrentUser"
                                class="rounded-full bg-foreground px-2.5 py-0.5 text-[11px] font-medium text-background"
                            >
                                You
                            </span>
                        </div>

                        <p class="text-[15px] text-muted-foreground">
                            {{ handle }}
                        </p>

                        <p
                            v-if="profileUser.bio"
                            class="max-w-3xl text-[15px] leading-relaxed text-foreground/85"
                        >
                            {{ profileUser.bio }}
                        </p>

                        <!-- Section chips -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="section in profileUser.sections"
                                :key="section"
                                class="rounded-full bg-muted px-3 py-1 text-[13px] font-medium text-foreground"
                            >
                                {{ section }}
                            </span>
                            <span
                                v-if="profileUser.sections.length === 0"
                                class="rounded-full bg-muted px-3 py-1 text-[13px] font-medium text-muted-foreground"
                            >
                                No section
                            </span>
                        </div>

                        <!-- Meta line -->
                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5" />
                                Joined {{ profileUser.joinedAt }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Flame class="h-3.5 w-3.5" />
                                {{ profileUser.streak }}-day streak
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 sm:hidden"
                            >
                                <Trophy class="h-3.5 w-3.5" />
                                Rank #{{ stats.rank }}
                            </span>
                        </div>
                    </div>

                    <!-- ════════════ Follower-style counts ════════════ -->
                    <div
                        class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-y border-border/50 py-3.5 sm:gap-9"
                    >
                        <button
                            v-for="stat in countStats"
                            :key="stat.key"
                            type="button"
                            :disabled="
                                (stat.key === 'followers' ||
                                    stat.key === 'following') &&
                                stat.value === 0
                            "
                            class="flex items-baseline gap-1.5 disabled:cursor-default"
                            :class="
                                stat.key === 'followers' ||
                                stat.key === 'following'
                                    ? 'cursor-pointer hover:opacity-80'
                                    : 'cursor-default'
                            "
                            :title="
                                stat.key === 'followers'
                                    ? 'See followers'
                                    : stat.key === 'following'
                                      ? 'See following'
                                      : undefined
                            "
                            @click="
                                stat.key === 'followers'
                                    ? openSocialList('followers')
                                    : stat.key === 'following'
                                      ? openSocialList('following')
                                      : undefined
                            "
                        >
                            <span
                                class="profile-metric text-[17px] text-foreground sm:text-[19px]"
                            >
                                {{ formatCount(stat.value) }}
                            </span>
                            <span
                                class="text-[13px] text-muted-foreground sm:text-[14px]"
                            >
                                {{ stat.label }}
                            </span>
                        </button>
                    </div>

                    <!-- ════════════ Section ranking cards ════════════ -->
                    <div
                        v-if="sectionRanks && sectionRanks.length > 0"
                        class="mt-4 grid gap-2 sm:grid-cols-2"
                    >
                        <Link
                            v-for="section in sectionRanks"
                            :key="section.id"
                            href="/leaderboard"
                            class="profile-card flex items-center gap-3 bg-card px-4 py-3 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                            >
                                <Trophy class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-[13px] font-semibold text-foreground"
                                >
                                    Rank #{{ section.rank }} of
                                    {{ section.total }}
                                </p>
                                <p
                                    class="truncate text-[12px] text-muted-foreground"
                                >
                                    {{ section.name }}
                                </p>
                            </div>
                        </Link>
                    </div>

                    <!-- ════════════ Positive kudos ════════════ -->
                    <div
                        v-if="!profileUser.isCurrentUser && isSameSection"
                        class="mt-4 flex flex-col gap-3 rounded-2xl border border-border/60 bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-sm font-semibold">Send a kudo</p>
                            <p class="text-xs text-muted-foreground">
                                A small, positive note for a classmate.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="kudo in kudoOptions"
                                :key="kudo.key"
                                type="button"
                                :disabled="
                                    kudoPending || viewerKudo === kudo.key
                                "
                                class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors disabled:cursor-default"
                                :class="
                                    viewerKudo === kudo.key
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border bg-background text-foreground hover:bg-muted'
                                "
                                @click="sendKudo(kudo.key)"
                            >
                                {{ kudo.label }}
                                <span
                                    v-if="kudos[kudo.key]"
                                    class="ml-1 opacity-70"
                                >
                                    {{ kudos[kudo.key] }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- ════════════ Recent kudos feed ════════════ -->
                    <div
                        v-if="recentKudos && recentKudos.length > 0"
                        class="mt-4 rounded-2xl border border-border/60 bg-card px-4 py-3"
                    >
                        <p class="text-sm font-semibold">Cheered on by</p>
                        <div
                            class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2"
                        >
                            <div
                                v-for="kudo in recentKudos"
                                :key="kudo.id"
                                class="flex items-center gap-2"
                            >
                                <Avatar class="size-7 rounded-full bg-muted">
                                    <AvatarImage
                                        v-if="kudo.avatar"
                                        :src="kudo.avatar"
                                        :alt="kudo.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback
                                        class="bg-muted text-[10px] font-semibold text-foreground"
                                    >
                                        {{ getInitials(kudo.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="text-[13px] text-foreground">
                                    {{ kudo.name }}
                                </span>
                                <span
                                    class="text-[11px] text-muted-foreground"
                                    :title="kudo.date ?? undefined"
                                >
                                    {{ kudoLabel[kudo.type] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ════════════ Segmented tabs ════════════ -->
                    <div
                        class="mt-5 inline-flex w-full gap-1 rounded-full bg-muted p-1 sm:w-auto"
                        role="tablist"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.key"
                            class="profile-segment flex-1 px-4 text-[14px] whitespace-nowrap transition-all sm:flex-none"
                            :class="
                                activeTab === tab.key
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                            <span class="ml-1 tabular-nums opacity-60">
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>

                    <!-- ════════════ Tab panels ════════════ -->
                    <div class="mt-5">
                        <!-- ── Activity feed ── -->
                        <div
                            v-show="activeTab === 'activity'"
                            class="space-y-3"
                            role="tabpanel"
                        >
                            <template v-if="history.length > 0">
                                <article
                                    v-for="item in history"
                                    :key="item.id"
                                    class="profile-card flex gap-3 bg-card p-4 transition-colors hover:bg-muted/40"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-foreground"
                                    >
                                        <component
                                            :is="iconForReason(item.reason)"
                                            class="h-[18px] w-[18px]"
                                        />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                                        >
                                            <span
                                                class="text-[15px] font-semibold text-foreground"
                                            >
                                                {{ item.reason }}
                                            </span>
                                            <span
                                                class="text-[13px] text-muted-foreground"
                                                :title="item.full_date"
                                            >
                                                · {{ item.date }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-0.5 text-[15px] leading-snug text-foreground/90"
                                        >
                                            {{ item.description }}
                                        </p>

                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                v-if="item.amount_xp !== 0"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-semibold tabular-nums"
                                                :class="
                                                    item.amount_xp < 0
                                                        ? 'bg-[#CB7676]/12 text-[#B65252]'
                                                        : 'bg-[#4D9375]/12 text-[#3F7B60]'
                                                "
                                            >
                                                <Zap class="h-3 w-3" />
                                                {{
                                                    formatDelta(item.amount_xp)
                                                }}
                                                XP
                                            </span>
                                            <span
                                                v-if="item.amount_points !== 0"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-semibold tabular-nums"
                                                :class="
                                                    item.amount_points < 0
                                                        ? 'bg-[#CB7676]/12 text-[#B65252]'
                                                        : 'bg-[#E0AF68]/15 text-[#9A7430]'
                                                "
                                            >
                                                <Trophy class="h-3 w-3" />
                                                {{
                                                    formatDelta(
                                                        item.amount_points,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="item.section"
                                                class="rounded-full bg-muted px-2.5 py-1 text-[12px] font-medium text-muted-foreground"
                                            >
                                                {{ item.section }}
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </template>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <Sparkles
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No activity yet
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    Earned XP and milestones will show up here.
                                </p>
                            </div>
                        </div>

                        <!-- ── Achievements ── -->
                        <div
                            v-show="activeTab === 'achievements'"
                            role="tabpanel"
                        >
                            <div
                                v-if="badges.length > 0"
                                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                            >
                                <div
                                    v-for="badge in badges"
                                    :key="badge.id"
                                    class="profile-card flex flex-col items-center gap-2.5 bg-card p-5 text-center transition-transform duration-200 hover:-translate-y-0.5"
                                    :class="!badge.earned && 'opacity-60'"
                                    :title="
                                        badge.earned
                                            ? 'Unlocked'
                                            : badge.requiredLevel
                                              ? `Reach Level ${badge.requiredLevel} to unlock`
                                              : 'Locked'
                                    "
                                >
                                    <div
                                        class="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-muted"
                                    >
                                        <img
                                            v-if="badge.image"
                                            :src="badge.image"
                                            :alt="badge.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <Shield
                                            v-else
                                            class="h-6 w-6 text-muted-foreground"
                                        />
                                        <!-- Lock badge on locked achievements -->
                                        <div
                                            v-if="!badge.earned"
                                            class="absolute inset-0 flex items-center justify-center rounded-full bg-background/60"
                                        >
                                            <Lock
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-0.5">
                                        <p
                                            class="text-[14px] leading-tight font-semibold"
                                        >
                                            {{ badge.name }}
                                        </p>
                                        <p
                                            v-if="
                                                badge.earned && badge.earnedAt
                                            "
                                            class="text-[12px] font-medium text-[#4D9375]"
                                        >
                                            Unlocked
                                            {{ badge.earnedAt }}
                                        </p>
                                        <p
                                            v-else-if="
                                                badge.earned &&
                                                badge.earnedSeason
                                            "
                                            class="text-[12px] text-muted-foreground"
                                        >
                                            {{ badge.earnedSeason }}
                                        </p>
                                        <p
                                            v-else-if="badge.requiredLevel"
                                            class="text-[12px] text-muted-foreground"
                                        >
                                            Level {{ badge.requiredLevel }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <Medal
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No badges yet
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    {{ profileUser.name }} hasn&apos;t unlocked
                                    any achievements.
                                </p>
                            </div>
                        </div>

                        <!-- ── Courses ── -->
                        <div
                            v-show="activeTab === 'courses'"
                            role="tabpanel"
                            class="space-y-3"
                        >
                            <p class="text-[13px] text-muted-foreground">
                                Visible because you share a section.
                            </p>

                            <template v-if="courses.length > 0">
                                <div
                                    v-for="course in courses"
                                    :key="course.id"
                                    class="profile-card bg-card p-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-[15px] font-semibold"
                                            >
                                                {{ course.name }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[13px] text-muted-foreground"
                                            >
                                                {{ course.completedLessons }} of
                                                {{ course.totalLessons }}
                                                lessons ·
                                                {{ course.xpEarned }} XP
                                            </p>
                                        </div>
                                        <span
                                            class="profile-metric shrink-0 text-[17px]"
                                        >
                                            {{ course.progress }}%
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground transition-all duration-500"
                                            :style="{
                                                width: `${course.progress}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </template>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <LayoutGrid
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No active courses
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    Nothing enrolled for the current season.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════ Followers / Following modal ════════════ -->
        <Dialog
            :open="socialModalOpen"
            @update:open="
                (open) => {
                    if (!open) activeSocialList = null;
                }
            "
        >
            <DialogContent
                class="overflow-hidden border-border/50 bg-card p-0 sm:max-w-[420px]"
            >
                <div
                    class="flex items-center justify-between border-b border-border/20 px-5 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                        >
                            <Users class="h-4 w-4" />
                        </div>
                        <DialogTitle
                            class="text-[16px] font-semibold tracking-tight"
                        >
                            {{ socialListTitle }}
                        </DialogTitle>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-muted"
                        aria-label="Close"
                        @click="activeSocialList = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="max-h-[55vh] scrollbar-none overflow-y-auto p-3">
                    <template v-if="socialListItems.length > 0">
                        <div
                            v-for="person in socialListItems"
                            :key="person.id"
                            class="flex items-center gap-3 rounded-xl p-2.5 transition-colors hover:bg-muted/40"
                        >
                            <Link
                                :href="`/u/${person.id}`"
                                class="flex min-w-0 flex-1 items-center gap-3"
                            >
                                <Avatar class="size-9 rounded-full bg-muted">
                                    <AvatarImage
                                        v-if="person.avatar"
                                        :src="person.avatar"
                                        :alt="person.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback
                                        class="bg-muted text-xs font-semibold text-foreground"
                                    >
                                        {{ getInitials(person.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span
                                    class="truncate text-[14px] font-medium text-foreground"
                                >
                                    {{ person.name }}
                                </span>
                            </Link>
                        </div>
                    </template>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-6 py-12 text-center"
                    >
                        <Users class="h-6 w-6 text-muted-foreground/50" />
                        <p class="text-[14px] font-medium text-foreground">
                            Nothing here yet
                        </p>
                        <p class="text-[13px] text-muted-foreground">
                            {{
                                socialListTitle === 'Followers'
                                    ? 'No one is following this student yet.'
                                    : 'Not following anyone yet.'
                            }}
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
