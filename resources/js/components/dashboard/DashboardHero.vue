<script setup lang="ts">
import { computed } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { X, Sparkles, Zap, Award, Megaphone, ArrowRight, RefreshCw } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

interface Announcement {
    id: number;
    title: string;
    description?: string;
    link?: string;
}

interface UserStats {
    level: number;
    totalXP: number;
    currentXP: number;
    maxXPForLevel: number;
    points: number;
}

interface Props {
    userName: string;
    userAvatar?: string;
    userStats: UserStats;
    announcements: Announcement[];
    totalXPProgress: number;
    timeBasedGreeting: string;
    isRefreshing?: boolean;
    lastSyncTime?: Date;
}

const props = defineProps<Props>();
const emit = defineEmits(['close-announcement', 'refresh']);

const animatedLevel = useNumberAnimation(() => props.userStats.level);
const animatedXP = useNumberAnimation(() => props.userStats.currentXP);
const animatedMaxXP = useNumberAnimation(() => props.userStats.maxXPForLevel);

const xpPercentage = computed(() => {
    if (!props.userStats.maxXPForLevel) return 0;
    const percent = (props.userStats.currentXP / props.userStats.maxXPForLevel) * 100;
    return Math.min(100, Math.max(0, percent));
});
</script>

<template>
    <div class="space-y-6">
        <!-- Integrated Announcement -->
        <TransitionGroup 
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-for="item in announcements.slice(0, 1)" :key="item.id" 
                class="relative group glass-morphism rounded-2xl sm:rounded-3xl p-3 sm:p-5 border border-primary/10 overflow-hidden shadow-2xl shadow-primary/5 hover:border-primary/30 transition-all duration-500 mb-4"
            >
                <div class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent"></div>
                <div class="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center flex-1 gap-3 sm:gap-4 w-full sm:w-auto">
                        <div class="shrink-0 w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center rounded-xl sm:rounded-2xl bg-primary/10 text-primary shadow-inner group-hover:bg-primary group-hover:text-primary-foreground transition-all duration-500">
                            <Megaphone class="w-5 h-5 sm:w-6 sm:h-6" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2">
                                <h4 class="text-xs sm:text-sm font-black tracking-tight text-foreground truncate uppercase tracking-widest">{{ item.title }}</h4>
                                <span class="px-1.5 py-0.5 rounded-md bg-primary/10 text-primary text-[7px] sm:text-[8px] font-black uppercase tracking-widest animate-pulse shrink-0">New</span>
                            </div>
                            <p v-if="item.description" class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 font-medium line-clamp-1 italic opacity-70">"{{ item.description }}"</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto pt-2 sm:pt-0 border-t border-primary/5 sm:border-0">
                        <Link v-if="item.link" :href="item.link" 
                            class="flex items-center justify-center gap-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-primary/10 hover:bg-primary text-primary hover:text-primary-foreground rounded-lg sm:rounded-xl text-[9px] sm:text-[10px] font-black uppercase tracking-widest transition-all duration-300 group/link flex-1 sm:flex-none"
                        >
                            Explore
                            <ArrowRight class="w-3 h-3 group-hover/link:translate-x-1 transition-transform" />
                        </Link>
                        <button @click="emit('close-announcement', item.id)" 
                            class="p-1.5 sm:p-2 rounded-lg sm:rounded-xl hover:bg-destructive/10 transition-colors text-muted-foreground hover:text-destructive group/close shrink-0"
                            title="Dismiss"
                        >
                            <X class="w-3.5 h-3.5 sm:w-4 h-4 group-hover/close:rotate-90 transition-transform" />
                        </button>
                    </div>
                </div>
            </div>
        </TransitionGroup>

        <!-- Bespoke Hero Section -->
        <div class="surface-card p-5 sm:p-7 relative overflow-hidden group">
            <!-- Decorative Elements -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-[100px] transition-transform duration-1000 group-hover:scale-150"></div>
            
            <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-8 sm:gap-10">
                <!-- Left side: Profile Picture + Greetings -->
                <div class="flex items-center gap-4 sm:gap-6">
                    <!-- Profile Picture -->
                    <div class="shrink-0">
                        <div class="relative group/avatar cursor-pointer">
                            <div class="absolute -inset-1 bg-gradient-to-tr from-primary/40 to-primary/0 rounded-full blur opacity-0 group-hover/avatar:opacity-100 transition duration-500"></div>
                            <Avatar class="size-16 sm:size-20 lg:size-24 border-2 border-primary/20 bg-card/60 relative overflow-hidden rounded-full transition-all duration-500 group-hover/avatar:scale-105 group-hover/avatar:border-primary/50 shadow-xl">
                                <AvatarImage
                                    v-if="userAvatar"
                                    :src="userAvatar"
                                    :alt="userName"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-primary/10 font-black text-primary text-lg sm:text-xl lg:text-2xl"
                                >
                                    {{ getInitials(userName) }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                    </div>

                    <div class="space-y-3 sm:space-y-4">
                        <div class="flex items-center gap-2.5 sm:gap-3 text-[9px] sm:text-[10px] font-black tracking-widest uppercase text-muted-foreground/60">
                            <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                <span class="w-1 h-1 rounded-full bg-emerald-500 animate-pulse"></span>
                                Online
                            </div>
                            <span class="w-1 h-1 rounded-full bg-border"></span>
                            <div class="flex items-center gap-2 group/sync">
                                <span class="whitespace-nowrap">Sync: {{ lastSyncTime ? lastSyncTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--:--' }}</span>
                                <button @click="emit('refresh')" :disabled="isRefreshing" class="hover:text-primary transition-colors">
                                    <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isRefreshing }" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-0.5 sm:space-y-1">
                            <h1 class="text-2xl sm:text-4xl font-black tracking-tighter premium-gradient-text leading-tight">
                                {{ timeBasedGreeting }}, {{ userName }}
                            </h1>
                            <p class="text-muted-foreground text-[10px] sm:text-sm font-medium max-w-[200px] sm:max-w-sm leading-relaxed opacity-80">
                                Your learning engine is at <span class="text-foreground font-black">peak performance</span>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Level & Progress Visual -->
                <div class="flex items-center gap-4 sm:gap-6 w-full lg:w-auto lg:ml-auto">

                    <div class="flex flex-col sm:flex-row items-center gap-5 sm:gap-8 bg-primary/5 p-6 sm:p-6 rounded-[2.5rem] border border-primary/10 backdrop-blur-sm relative group/progress-box flex-1 lg:flex-none">
                        <div class="relative shrink-0 p-1 group/level">
                            <!-- Core Level Chip -->
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full border-2 border-primary/20 bg-card/60 flex flex-col items-center justify-center shadow-lg relative z-10 backdrop-blur-2xl transition-all duration-500 group-hover:scale-105">
                                <span class="text-[7px] sm:text-[8px] uppercase font-black tracking-[0.2em] text-primary/60 mb-0.5">Level</span>
                                <span class="text-xl sm:text-2xl font-black font-mono tracking-tighter leading-none premium-gradient-text tabular-nums">{{ animatedLevel }}</span>
                                <Award class="w-2.5 h-2.5 sm:w-3 h-3 text-primary mt-1 opacity-60" />
                            </div>
                        </div>

                        <div class="flex-1 w-full sm:w-64 lg:w-72 space-y-3 relative z-10">
                            <div class="flex justify-between items-end">
                                <div class="space-y-0.5">
                                    <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground/60 tabular-nums">{{ Math.max(0, animatedMaxXP - animatedXP).toLocaleString() }} XP to Level {{ userStats.level + 1 }}</p>
                                    <p class="text-[10px] sm:text-xs font-black tracking-tight tabular-nums">{{ animatedXP.toLocaleString() }} <span class="text-muted-foreground/40 font-bold">/ {{ animatedMaxXP.toLocaleString() }} XP</span></p>
                                </div>
                                <div class="flex items-center gap-1 text-[10px] sm:text-xs font-black text-primary tabular-nums">
                                    <Zap class="w-3 h-3 fill-current" />
                                    <span>{{ Math.round(xpPercentage) }}%</span>
                                </div>
                            </div>
                            
                            <div class="relative h-1.5 sm:h-2 w-full bg-muted/40 rounded-full overflow-hidden border border-border/10">
                                <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out relative shadow-[0_0_15px_rgba(var(--primary),0.3)]" 
                                    :style="{ width: `${xpPercentage}%` }">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes spin-reverse {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(100%); }
    100% { transform: translateX(100%); }
}
</style>
