<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { Calendar, Shield, Trophy, LayoutGrid, Zap, Target } from 'lucide-vue-next';
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
    icon: string;
}

const props = defineProps<{
    profileUser: {
        id: number;
        name: string;
        avatar: string | null;
        cover_photo: string | null;
        section: string | null;
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
    isSameSection: boolean;
}>();

const { getInitials } = useInitials();

const breadcrumbItems = [
    { title: 'Dashboard', href: dashboard() },
    { title: props.profileUser.name, href: `/u/${props.profileUser.id}` },
];
</script>

<template>
    <Head :title="`${profileUser.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="p-4 md:p-8 space-y-8 relative overflow-hidden bg-background">
            
            <div class="absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Profile Banner & Avatar Container -->
            <div class="relative mb-20 md:mb-24">
                <!-- Profile Banner -->
                <div class="relative w-full h-48 md:h-64 rounded-2xl md:rounded-[2rem] bg-gradient-to-br from-primary/10 via-primary/5 to-background border border-primary/10 overflow-hidden">
                    
                    <img v-if="profileUser.cover_photo" :src="profileUser.cover_photo" class="absolute inset-0 w-full h-full object-cover z-0" />
                    
                    <!-- Abstract patterns -->
                    <div class="absolute inset-0 opacity-20 dark:opacity-10 mix-blend-overlay z-10 pointer-events-none">
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon fill="currentColor" points="0,100 100,0 100,100"/>
                        </svg>
                    </div>
                </div>
                
                <!-- Avatar Floating Over Banner -->
                <div class="absolute -bottom-16 left-8 md:left-12 flex items-end gap-6 z-20">
                    <Avatar class="h-32 w-32 md:h-40 md:w-40 border-4 border-background shadow-2xl bg-muted">
                        <AvatarImage v-if="profileUser.avatar" :src="profileUser.avatar" class="object-cover" />
                        <AvatarFallback class="text-4xl font-bold bg-primary/20 text-foreground">{{ getInitials(profileUser.name) }}</AvatarFallback>
                    </Avatar>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="px-4 md:px-12 grid grid-cols-1 lg:grid-cols-3 gap-8 z-10 relative">
                
                <!-- Left Sidebar Details -->
                <div class="space-y-6">
                    <div>
                        <h1 class="text-3xl md:text-4xl font-black tracking-tight">{{ profileUser.name }}</h1>
                        <div class="flex flex-wrap items-center gap-3 mt-2">
                            <span v-if="profileUser.isCurrentUser" class="px-2 py-0.5 rounded-md bg-muted text-xs font-bold text-muted-foreground uppercase tracking-widest border border-border/40">You</span>
                            <span v-if="profileUser.section" class="px-3 py-1 rounded-full bg-primary/10 text-xs font-bold text-primary uppercase tracking-widest border border-primary/20">{{ profileUser.section }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-3 text-sm text-foreground/80 font-medium">
                            <Calendar class="w-4 h-4 text-primary" />
                            Joined {{ profileUser.joinedAt }}
                        </div>
                        <div class="flex items-center gap-3 text-sm text-foreground/80 font-medium">
                            <Zap class="w-4 h-4 text-amber-500" />
                            {{ profileUser.streak }} Day Streak
                        </div>
                        <div class="flex items-center gap-3 text-sm text-foreground/80 font-medium">
                            <Trophy class="w-4 h-4 text-emerald-500" />
                            Rank #{{ stats.rank }} <span class="text-muted-foreground opacity-60">of {{ stats.totalPlayers }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right Content Grid -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Top Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="p-6 rounded-2xl bg-card border border-border/50 shadow-sm flex flex-col justify-center items-center gap-2">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-widest">Level</span>
                            <span class="text-4xl font-black text-primary">{{ stats.level }}</span>
                        </div>
                        <div class="p-6 rounded-2xl bg-card border border-border/50 shadow-sm flex flex-col justify-center items-center gap-2">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-widest">Season XP</span>
                            <span class="text-4xl font-black text-foreground">{{ stats.xp }}</span>
                        </div>
                        <div class="p-6 rounded-2xl bg-card border border-border/50 shadow-sm flex flex-col justify-center items-center gap-2 md:col-span-1 col-span-2">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-widest">Badges</span>
                            <span class="text-4xl font-black text-amber-500">{{ stats.badgesCount }}</span>
                        </div>
                    </div>

                    <!-- Shared Courses (If same section) -->
                    <div v-if="isSameSection" class="space-y-4">
                        <h3 class="text-xl font-bold flex items-center gap-2">
                            <LayoutGrid class="w-5 h-5 text-primary" />
                            Active Courses
                        </h3>
                        <p class="text-xs text-muted-foreground -mt-3 mb-2">You can see this because you share the same section.</p>
                        
                        <div v-if="courses.length > 0" class="grid gap-3">
                            <div v-for="course in courses" :key="course.id" class="p-4 rounded-xl bg-muted/40 border border-border/40 flex items-center justify-between transition-colors hover:bg-muted/60">
                                <div>
                                    <h4 class="font-bold text-sm">{{ course.name }}</h4>
                                    <p class="text-[10px] text-muted-foreground font-medium uppercase tracking-wider mt-1">{{ course.completedLessons }} / {{ course.totalLessons }} Lessons Completed</p>
                                </div>
                                <div class="px-3 py-1 rounded bg-primary/10 text-primary font-black text-xs">
                                    {{ course.progress }}%
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-8 rounded-xl bg-card border border-dashed text-center text-muted-foreground text-sm">
                            No active courses found for the current season.
                        </div>
                    </div>

                    <!-- Badges Grid -->
                    <div class="space-y-4 pt-4">
                        <h3 class="text-xl font-bold flex items-center gap-2">
                            <Shield class="w-5 h-5 text-primary" />
                            Achievements
                        </h3>
                        <div v-if="badges.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div v-for="badge in badges" :key="badge.id" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-gradient-to-br from-card/80 to-card border border-border/50 shadow-sm hover:scale-105 transition-transform duration-300">
                                <div class="w-12 h-12 mb-3 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xl" v-html="badge.icon">
                                </div>
                                <span class="text-xs font-bold text-center leading-tight">{{ badge.name }}</span>
                            </div>
                        </div>
                        <div v-else class="p-8 rounded-xl bg-card border border-dashed text-center text-muted-foreground text-sm">
                            {{ profileUser.name }} hasn't unlocked any badges yet.
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </AppLayout>
</template>
