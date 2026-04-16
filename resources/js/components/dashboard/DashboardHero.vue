<script setup lang="ts">
import { computed } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { X, Sparkles, Zap, Award, Megaphone, ArrowRight, RefreshCw } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

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
        <div class="surface-card p-4 sm:p-7 relative overflow-hidden group">
            <!-- Decorative Elements -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-[100px] transition-transform duration-1000 group-hover:scale-150"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-8">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-black tracking-widest uppercase border border-primary/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                            System Online
                        </div>
                        
                        <!-- System Sync Indicator -->
                        <div class="flex items-center gap-2 px-3 py-0.5 rounded-full bg-muted/30 text-muted-foreground text-[10px] font-black tracking-widest uppercase border border-border/40 group/sync">
                            <span>Sync: {{ lastSyncTime ? lastSyncTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--:--' }}</span>
                            <button 
                                @click="emit('refresh')"
                                class="hover:text-primary transition-colors"
                                :disabled="isRefreshing"
                            >
                                <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isRefreshing }" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-500 text-[8px] font-black uppercase tracking-widest border border-emerald-500/20">Active Session</span>
                            <span class="text-[8px] font-bold text-muted-foreground/40 uppercase tracking-widest">{{ new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}</span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tighter premium-gradient-text leading-[1.1]">
                            {{ timeBasedGreeting }}, {{ userName }}
                        </h1>
                        <p class="text-muted-foreground text-xs sm:text-sm font-medium max-w-sm leading-relaxed opacity-80">
                            Ready to transcend your limits? Your learning engine is primed and at <span class="text-foreground font-black">peak performance</span>.
                        </p>
                    </div>
                </div>

                <!-- Level & Progress Visual -->
                <div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8 bg-primary/5 p-5 sm:p-6 rounded-[2rem] sm:rounded-[2.5rem] border border-primary/10 backdrop-blur-sm relative group/progress-box w-full lg:w-auto">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover/progress-box:opacity-100 transition-opacity duration-700 rounded-[2rem] sm:rounded-[2.5rem]"></div>
                    
                    <div class="relative shrink-0 p-2 group/level">
                        <!-- Orbiting Glow Bloom -->
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-[30px] opacity-0 group-hover/level:opacity-100 transition-opacity duration-700 scale-50 group-hover/level:scale-110"></div>
                        
                        <!-- Futuristic Orbital System -->
                        <div class="absolute inset-0">
                            <!-- Outer Tech Ring -->
                            <div class="absolute inset-0 border border-dashed border-primary/30 rounded-full animate-[spin_20s_linear_infinite]"></div>
                            <!-- Middle Pulse Ring -->
                            <div class="absolute inset-2 border border-primary/20 rounded-full animate-[pulse_4s_ease-in-out_infinite]"></div>
                        </div>
                        
                        <!-- Core Level Chip -->
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full border-2 border-primary/30 bg-card/60 flex flex-col items-center justify-center shadow-[0_0_50px_-12px_rgba(var(--primary),0.3)] relative z-10 backdrop-blur-2xl group-hover:border-primary/60 transition-all duration-500 group-hover:scale-105">
                            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-primary/10 to-transparent"></div>
                            <span class="text-[8px] uppercase font-black tracking-[0.2em] text-primary/60 mb-0.5">Level</span>
                            <span class="text-xl sm:text-2xl font-black font-mono tracking-tighter leading-none premium-gradient-text">{{ animatedLevel }}</span>
                            <div class="mt-1 flex items-center justify-center relative">
                                <div class="absolute inset-0 blur-lg bg-primary/30 rounded-full animate-pulse"></div>
                                <Award class="w-3 h-3 text-primary relative z-10" />
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 w-full sm:w-64 lg:w-72 space-y-3 sm:space-y-4 relative z-10">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-2 sm:gap-0">
                            <div class="space-y-1 w-full sm:w-auto">
                                <div class="flex items-center flex-wrap gap-2">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-primary">Neural Progress</p>
                                    <span class="hidden sm:block w-1 h-1 rounded-full bg-primary/40"></span>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-muted-foreground/60">{{ Math.max(0, animatedMaxXP - animatedXP).toLocaleString() }} XP to Level {{ userStats.level + 1 }}</p>
                                </div>
                                <p class="text-[11px] sm:text-xs font-black tracking-tight">{{ animatedXP.toLocaleString() }} <span class="text-muted-foreground/40 font-bold">/ {{ animatedMaxXP.toLocaleString() }} XP</span></p>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs sm:text-sm font-black text-primary self-end sm:self-auto">
                                <Zap class="w-3 h-3 sm:w-3.5 sm:h-3.5 fill-current animate-pulse" />
                                <span>{{ Math.round(xpPercentage) }}%</span>
                            </div>
                        </div>
                        
                        <div class="relative h-3 sm:h-4 w-full bg-muted/40 rounded-full p-0.5 sm:p-1 border border-border/20 shadow-inner">
                            <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out relative shadow-[0_0_20px_rgba(var(--primary),0.4)]" 
                                :style="{ width: `${xpPercentage}%` }">
                                <!-- Ghost Shimmer -->
                                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full animate-[shimmer_3s_infinite]"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 sm:gap-4 overflow-hidden">
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full bg-primary"></div>
                                    <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest text-muted-foreground/60">Core</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full bg-primary/30"></div>
                                    <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest text-muted-foreground/60">Sub</span>
                                </div>
                            </div>
                            <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-primary animate-pulse whitespace-nowrap">Synchronizing...</span>
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
