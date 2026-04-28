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
    <div class="space-y-6 lg:space-y-8">
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
                class="relative group bg-card/30 backdrop-blur-xl border border-border/40 rounded-2xl sm:rounded-3xl p-3 sm:p-5 border-primary/10 overflow-hidden shadow-2xl shadow-primary/5 hover:border-primary/30 transition-all duration-500 mb-4"
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

        <!-- Bespoke Hero Section - Open Layout -->
        <div class="relative min-h-[160px] lg:min-h-[200px] flex flex-col justify-center px-2">
            <!-- Expansive Ambient Background - Subdued -->
            <div class="absolute inset-0 -z-10 pointer-events-none overflow-hidden rounded-[2.5rem]">
                <div class="absolute -top-1/2 -right-1/4 w-[150%] h-[200%] bg-gradient-to-br from-primary/[0.03] via-transparent to-transparent opacity-50 blur-[100px] animate-slow-drift"></div>
                <div class="absolute -bottom-1/2 -left-1/4 w-[150%] h-[200%] bg-gradient-to-tr from-primary/[0.03] via-transparent to-transparent opacity-30 blur-[100px] animate-slow-drift-reverse"></div>
            </div>
            
            <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-6 sm:gap-10">
                <!-- Left side: Profile Picture + Greetings -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6">
                    <!-- Profile Picture with Dynamic Glow -->
                    <div class="shrink-0 relative group/avatar">
                        <div class="absolute -inset-2 bg-primary/5 rounded-full blur-2xl opacity-0 group-hover/avatar:opacity-100 transition-opacity duration-700"></div>
                        <div class="relative">
                            <!-- Online Badge integrated into Avatar border -->
                            <div class="absolute -bottom-0.5 -right-0.5 z-20 w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-background flex items-center justify-center border-2 border-background shadow-lg">
                                <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.4)]"></span>
                            </div>
                            
                            <Avatar class="size-16 sm:size-20 lg:size-24 border border-primary/20 bg-card/40 backdrop-blur-md relative overflow-hidden rounded-2xl transition-all duration-700 group-hover/avatar:scale-105 group-hover/avatar:rotate-2 group-hover/avatar:border-primary/50 shadow-xl">
                                <AvatarImage
                                    v-if="userAvatar"
                                    :src="userAvatar"
                                    :alt="userName"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-primary/5 font-black text-primary text-xl sm:text-2xl lg:text-3xl"
                                >
                                    {{ getInitials(userName) }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                    </div>

                    <div class="space-y-3 sm:space-y-4">
                        <div class="flex items-center gap-2 text-[9px] sm:text-[10px] font-black tracking-[0.2em] uppercase text-muted-foreground/40">
                            <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-primary/5 border border-primary/10 group/sync cursor-pointer hover:bg-primary/10 transition-colors" @click="emit('refresh')">
                                <RefreshCw class="w-2.5 h-2.5 text-primary/60" :class="{ 'animate-spin': isRefreshing }" />
                                <span class="whitespace-nowrap tabular-nums">Sync: {{ lastSyncTime ? lastSyncTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--:--' }}</span>
                            </div>
                            <span class="w-1 h-1 rounded-full bg-border/30"></span>
                            <span class="opacity-50">Active</span>
                        </div>

                        <div class="space-y-1">
                            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight bg-gradient-to-br from-foreground via-foreground to-foreground/40 bg-clip-text text-transparent leading-[1.1] sm:leading-[1.05]">
                                {{ timeBasedGreeting }}, {{ userName }}
                            </h1>
                            <p class="text-muted-foreground/60 text-[10px] sm:text-sm font-medium max-w-sm sm:max-w-md leading-relaxed">
                                Your engine is at <span class="text-primary font-black relative px-0.5">peak capacity</span>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right side: Floating Progress Panel - Compacted -->
                <div class="lg:w-auto lg:ml-auto w-full group/progress">
                    <div class="relative p-4 sm:p-6 rounded-3xl bg-card/20 backdrop-blur-xl border border-white/5 dark:border-white/[0.02] shadow-xl transition-all duration-700 group-hover/progress:translate-y-[-2px]">
                        
                        <div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8 relative z-10">
                            <!-- Level Indicator - Sleeker -->
                            <div class="relative shrink-0">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl border border-primary/20 bg-background/30 flex flex-col items-center justify-center shadow-lg backdrop-blur-xl transition-all duration-700 group-hover/progress:scale-105">
                                    <span class="text-[7px] sm:text-[8px] uppercase font-black tracking-widest text-primary/40">Lvl</span>
                                    <span class="text-2xl sm:text-3xl font-black font-mono tracking-tighter leading-none bg-gradient-to-br from-foreground via-foreground to-foreground/40 bg-clip-text text-transparent tabular-nums">{{ animatedLevel }}</span>
                                </div>
                            </div>

                            <div class="flex-1 w-full sm:w-60 lg:w-64 space-y-3">
                                <div class="flex justify-between items-end">
                                    <div class="space-y-0.5">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-muted-foreground/30 tabular-nums">
                                            Next: <span class="text-primary/60">{{ Math.max(0, animatedMaxXP - animatedXP).toLocaleString() }} XP</span>
                                        </p>
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-xl font-black tracking-tight tabular-nums">{{ animatedXP.toLocaleString() }}</span>
                                            <span class="text-[10px] font-bold text-muted-foreground/20 uppercase tracking-widest">/ {{ animatedMaxXP.toLocaleString() }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <div class="flex items-center gap-1 text-base font-black text-primary tabular-nums leading-none">
                                            <Zap class="size-3 fill-current" />
                                            <span>{{ Math.round(xpPercentage) }}%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="relative h-2 w-full bg-muted/20 rounded-full overflow-hidden border border-white/5 shadow-inner">
                                    <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out relative shadow-[0_0_12px_rgba(var(--primary),0.3)]" 
                                        :style="{ width: `${xpPercentage}%` }">
                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent w-1/2 -skew-x-[45deg] animate-shimmer"></div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center px-1">
                                    <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/30">Tier {{ Math.floor(userStats.level / 10) + 1 }}</span>
                                    <div class="flex gap-1 opacity-50">
                                        <div v-for="i in 5" :key="i" class="w-1 h-1 rounded-full" :class="i <= (userStats.level % 5) + 1 ? 'bg-primary' : 'bg-muted/40'"></div>
                                    </div>
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
@keyframes slow-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(5%, 5%) scale(1.1); }
}

@keyframes slow-drift-reverse {
    0%, 100% { transform: translate(0, 0) scale(1.1); }
    50% { transform: translate(-5%, -5%) scale(1); }
}

@keyframes shimmer {
    0% { transform: translateX(-200%) skew-x(-45deg); }
    100% { transform: translateX(200%) skew-x(-45deg); }
}

.animate-slow-drift {
    animation: slow-drift 20s infinite ease-in-out;
}

.animate-slow-drift-reverse {
    animation: slow-drift-reverse 25s infinite ease-in-out;
}

.animate-shimmer {
    animation: shimmer 3s infinite ease-in-out;
}

</style>

