<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    Shield,
    Trophy,
    LayoutGrid,
    Zap,
    Target,
} from 'lucide-vue-next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

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
    earnedSeason?: string | null;
    earnedAt?: string | null;
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
        sections: string[];
        streak: number;
        joinedAt: string;
        isCurrentUser: boolean;
    };
    stats: {
        level: number;
        xp: number;
        rank: number;
        totalPlayers: number;
        badgesCount: number;
    };
    badges: Badge[];
    courses: Course[];
    history: HistoryItem[];
    isSameSection: boolean;
}>();

const { getInitials } = useInitials();

const breadcrumbItems = [
    { title: 'Dashboard', href: dashboard() },
    { title: props.profileUser.name, href: `/u/${props.profileUser.id}` },
];

const formatDelta = (value: number) => {
    return value >= 0 ? `+${value}` : `${value}`;
};
</script>

<template>
    <Head :title="`${profileUser.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div
            class="relative space-y-8 overflow-hidden bg-background p-4 md:p-8"
        >
            <div
                class="pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>

            <!-- Profile Banner & Avatar Container -->
            <div class="relative mb-20 md:mb-24">
                <!-- Profile Banner -->
                <div
                    class="relative h-48 w-full overflow-hidden rounded-2xl border border-primary/10 bg-gradient-to-br from-primary/10 via-primary/5 to-background md:h-64 md:rounded-[2rem]"
                >
                    <img
                        v-if="profileUser.cover_photo"
                        :src="profileUser.cover_photo"
                        class="absolute inset-0 z-0 h-full w-full object-cover"
                    />

                    <!-- Abstract patterns -->
                    <div
                        class="pointer-events-none absolute inset-0 z-10 opacity-20 mix-blend-overlay dark:opacity-10"
                    >
                        <svg
                            class="h-full w-full"
                            viewBox="0 0 100 100"
                            preserveAspectRatio="none"
                        >
                            <polygon
                                fill="currentColor"
                                points="0,100 100,0 100,100"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Avatar Floating Over Banner -->
                <div
                    class="absolute -bottom-16 left-8 z-20 flex items-end gap-6 md:left-12"
                >
                    <Avatar
                        class="h-32 w-32 border-4 border-background bg-muted shadow-2xl md:h-40 md:w-40"
                    >
                        <AvatarImage
                            v-if="profileUser.avatar"
                            :src="profileUser.avatar"
                            class="object-cover"
                        />
                        <AvatarFallback
                            class="bg-primary/20 text-4xl font-bold text-foreground"
                            >{{ getInitials(profileUser.name) }}</AvatarFallback
                        >
                    </Avatar>
                </div>
            </div>

            <!-- Profile Details -->
            <div
                class="relative z-10 grid grid-cols-1 gap-8 px-4 md:px-12 lg:grid-cols-3"
            >
                <!-- Left Sidebar Details -->
                <div class="space-y-6">
                    <div>
                        <h1
                            class="text-3xl font-black tracking-tight md:text-4xl"
                        >
                            {{ profileUser.name }}
                        </h1>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <span
                                v-if="profileUser.isCurrentUser"
                                class="rounded-md border border-border/40 bg-muted px-2 py-0.5 text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                >You</span
                            >
                            <span
                                v-for="section in profileUser.sections"
                                :key="section"
                                class="rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-bold tracking-widest text-primary uppercase"
                                >{{ section }}</span
                            >
                            <span
                                v-if="profileUser.sections.length === 0"
                                class="rounded-full border border-border/40 bg-muted/50 px-3 py-1 text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                >No Section</span
                            >
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex items-center gap-3 text-sm font-medium text-foreground/80"
                        >
                            <Calendar class="h-4 w-4 text-primary" />
                            Joined {{ profileUser.joinedAt }}
                        </div>
                        <div
                            class="flex items-center gap-3 text-sm font-medium text-foreground/80"
                        >
                            <Zap class="h-4 w-4 text-amber-500" />
                            {{ profileUser.streak }} Day Streak
                        </div>
                        <div
                            class="flex items-center gap-3 text-sm font-medium text-foreground/80"
                        >
                            <Trophy class="h-4 w-4 text-emerald-500" />
                            Rank #{{ stats.rank }}
                            <span class="text-muted-foreground opacity-60"
                                >of {{ stats.totalPlayers }}</span
                            >
                        </div>
                    </div>

                    <!-- Gamification History Section (Desktop) -->
                    <div
                        class="hidden space-y-4 border-t border-border/40 pt-4 lg:block"
                    >
                        <h3 class="flex items-center gap-2 text-xl font-bold">
                            <Trophy class="h-5 w-5 text-primary" />
                            History
                        </h3>
                        <div
                            class="custom-scrollbar max-h-[380px] space-y-3 overflow-y-auto pr-2"
                        >
                            <div
                                v-if="history.length > 0"
                                v-for="item in history"
                                :key="item.id"
                                class="group rounded-xl border border-border/50 bg-card p-3 shadow-sm transition-colors hover:bg-muted/30"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="space-y-1">
                                        <h4
                                            class="text-xs font-black tracking-widest text-primary uppercase"
                                        >
                                            {{ item.reason }}
                                        </h4>
                                        <p
                                            class="line-clamp-2 text-xs leading-tight font-bold"
                                        >
                                            {{ item.description }}
                                        </p>
                                        <p
                                            v-if="item.section"
                                            class="text-[10px] font-bold text-muted-foreground"
                                        >
                                            {{ item.section }}
                                        </p>
                                        <p
                                            class="text-[10px] font-medium text-muted-foreground opacity-60"
                                            :title="item.full_date"
                                        >
                                            {{ item.date }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="flex items-center gap-1 rounded bg-primary/10 px-2 py-0.5 text-[10px] font-black text-primary"
                                            :class="{
                                                'bg-red-500/10 text-red-500':
                                                    item.amount_xp < 0,
                                            }"
                                        >
                                            <Zap class="h-2.5 w-2.5" />
                                            {{ formatDelta(item.amount_xp) }}
                                        </span>
                                        <span
                                            class="flex items-center gap-1 rounded bg-amber-500/10 px-2 py-0.5 text-[10px] font-black text-amber-500"
                                            :class="{
                                                'bg-red-500/10 text-red-500':
                                                    item.amount_points < 0,
                                            }"
                                        >
                                            <Trophy class="h-2.5 w-2.5" />
                                            {{
                                                formatDelta(item.amount_points)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="rounded-xl border border-dashed bg-card p-8 text-center text-xs font-medium text-muted-foreground"
                            >
                                No history available yet.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Content Grid -->
                <div class="space-y-8 lg:col-span-2">
                    <!-- Top Stats Cards -->
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        <div
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-border/50 bg-card p-6 shadow-sm"
                        >
                            <span
                                class="text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                >Level</span
                            >
                            <span class="text-4xl font-black text-primary">{{
                                stats.level
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-border/50 bg-card p-6 shadow-sm"
                        >
                            <span
                                class="text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                >Season XP</span
                            >
                            <span class="text-4xl font-black text-foreground">{{
                                stats.xp
                            }}</span>
                        </div>
                        <div
                            class="col-span-2 flex flex-col items-center justify-center gap-2 rounded-2xl border border-border/50 bg-card p-6 shadow-sm md:col-span-1"
                        >
                            <span
                                class="text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                >Badges</span
                            >
                            <span class="text-4xl font-black text-amber-500">{{
                                stats.badgesCount
                            }}</span>
                        </div>
                    </div>

                    <!-- Gamification History Section (Mobile) -->
                    <div class="space-y-4 pt-4 lg:hidden">
                        <h3 class="flex items-center gap-2 text-xl font-bold">
                            <Trophy class="h-5 w-5 text-primary" />
                            History
                        </h3>
                        <div
                            class="custom-scrollbar max-h-[420px] space-y-3 overflow-y-auto pr-2"
                        >
                            <div
                                v-if="history.length > 0"
                                v-for="item in history"
                                :key="item.id"
                                class="rounded-xl border border-border/50 bg-card p-4 shadow-sm transition-colors hover:bg-muted/30"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <h4
                                            class="text-xs font-black tracking-widest text-primary uppercase"
                                        >
                                            {{ item.reason }}
                                        </h4>
                                        <p
                                            class="text-sm leading-tight font-bold"
                                        >
                                            {{ item.description }}
                                        </p>
                                        <p
                                            v-if="item.section"
                                            class="text-[10px] font-bold text-muted-foreground"
                                        >
                                            {{ item.section }}
                                        </p>
                                        <p
                                            class="text-[10px] font-medium text-muted-foreground opacity-60"
                                        >
                                            {{ item.date }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 flex-col items-end gap-1.5"
                                    >
                                        <span
                                            class="flex items-center gap-1 rounded bg-primary/10 px-2 py-1 text-xs font-black text-primary"
                                            :class="{
                                                'bg-red-500/10 text-red-500':
                                                    item.amount_xp < 0,
                                            }"
                                        >
                                            <Zap class="h-3 w-3" />
                                            {{ formatDelta(item.amount_xp) }}
                                        </span>
                                        <span
                                            class="flex items-center gap-1 rounded bg-amber-500/10 px-2 py-1 text-xs font-black text-amber-500"
                                            :class="{
                                                'bg-red-500/10 text-red-500':
                                                    item.amount_points < 0,
                                            }"
                                        >
                                            <Trophy class="h-3 w-3" />
                                            {{
                                                formatDelta(item.amount_points)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
                            >
                                No history available yet.
                            </div>
                        </div>
                    </div>

                    <!-- Shared Courses (If same section) -->
                    <div v-if="isSameSection" class="space-y-4">
                        <h3 class="flex items-center gap-2 text-xl font-bold">
                            <LayoutGrid class="h-5 w-5 text-primary" />
                            Active Courses
                        </h3>
                        <p class="-mt-3 mb-2 text-xs text-muted-foreground">
                            You can see this because you share the same section.
                        </p>

                        <div v-if="courses.length > 0" class="grid gap-3">
                            <div
                                v-for="course in courses"
                                :key="course.id"
                                class="flex items-center justify-between rounded-xl border border-border/40 bg-muted/40 p-4 transition-colors hover:bg-muted/60"
                            >
                                <div>
                                    <h4 class="text-sm font-bold">
                                        {{ course.name }}
                                    </h4>
                                    <p
                                        class="mt-1 text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                                    >
                                        {{ course.completedLessons }} /
                                        {{ course.totalLessons }} Lessons
                                        Completed
                                    </p>
                                </div>
                                <div
                                    class="rounded bg-primary/10 px-3 py-1 text-xs font-black text-primary"
                                >
                                    {{ course.progress }}%
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
                        >
                            No active courses found for the current season.
                        </div>
                    </div>

                    <!-- Badges Grid -->
                    <div class="space-y-4 pt-4">
                        <h3 class="flex items-center gap-2 text-xl font-bold">
                            <Shield class="h-5 w-5 text-primary" />
                            Achievements
                        </h3>
                        <div
                            v-if="badges.length > 0"
                            class="grid grid-cols-2 gap-4 md:grid-cols-4"
                        >
                            <div
                                v-for="badge in badges"
                                :key="badge.id"
                                class="flex flex-col items-center justify-center rounded-2xl border border-border/50 bg-gradient-to-br from-card/80 to-card p-4 shadow-sm transition-transform duration-300 hover:scale-105"
                            >
                                <div
                                    class="mb-3 flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-primary/10"
                                >
                                    <img
                                        v-if="badge.image"
                                        :src="badge.image"
                                        :alt="badge.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="text-[10px] font-black tracking-widest text-primary uppercase"
                                        >Lvl
                                        {{ badge.requiredLevel ?? '--' }}</span
                                    >
                                </div>
                                <span
                                    class="text-center text-xs leading-tight font-bold"
                                    >{{ badge.name }}</span
                                >
                                <span
                                    v-if="badge.earnedSeason"
                                    class="mt-1 text-center text-[10px] text-muted-foreground"
                                >
                                    Earned in {{ badge.earnedSeason }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
                        >
                            {{ profileUser.name }} hasn't unlocked any badges
                            yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--primary) / 0.1);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--primary) / 0.2);
}
</style>
