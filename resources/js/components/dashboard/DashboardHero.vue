<script setup lang="ts">
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { X, Sparkles, Zap, Award } from 'lucide-vue-next';

interface Announcement {
    id: number;
    title: string;
    description?: string;
}

interface UserStats {
    level: number;
    totalXP: number;
    points: number;
}

interface Props {
    userName: string;
    userStats: UserStats;
    announcements: Announcement[];
    totalXPProgress: number;
    timeBasedGreeting: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['close-announcement']);

const animatedLevel = useNumberAnimation(() => props.userStats.level);
const animatedXP = useNumberAnimation(() => props.userStats.totalXP);

const maxXPForLevel = 500000; // Hardcoded for now based on the previous file's logic
const xpPercentage = (props.userStats.totalXP / maxXPForLevel) * 100;
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
                class="relative group glass-morphism rounded-3xl p-4 sm:p-5 border border-primary/10 overflow-hidden shadow-2xl shadow-primary/5"
            >
                <div class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent"></div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex shrink-0 w-12 h-12 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-inner">
                            <Sparkles class="w-6 h-6 animate-pulse" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-foreground">{{ item.title }}</h4>
                            <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5">{{ item.description }}</p>
                        </div>
                    </div>
                    <button @click="emit('close-announcement', item.id)" 
                        class="p-2 rounded-xl hover:bg-foreground/5 transition-colors text-muted-foreground hover:text-foreground"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </TransitionGroup>

        <!-- Bespoke Hero Section -->
        <div class="surface-card p-4 sm:p-7 relative overflow-hidden group">
            <!-- Decorative Elements -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-[100px] transition-transform duration-1000 group-hover:scale-150"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-8">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold tracking-widest uppercase">System Online</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-tighter premium-gradient-text leading-[1.1]">
                        {{ timeBasedGreeting }}, {{ userName }}
                    </h1>
                    <p class="text-muted-foreground text-xs sm:text-sm font-medium max-w-sm leading-relaxed">
                        Ready to transcend your limits? Your learning engine is primed and at <span class="text-foreground font-bold">peak performance</span>.
                    </p>
                </div>

                <!-- Level & Progress Visual -->
                <div class="flex items-center gap-6">
                    <div class="relative shrink-0 p-4 group/level">
                        <!-- Orbiting Glow Bloom -->
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-[30px] opacity-0 group-hover/level:opacity-100 transition-opacity duration-700 scale-50 group-hover/level:scale-110"></div>
                        
                        <!-- Futuristic Orbital System -->
                        <div class="absolute inset-0">
                            <!-- Outer Tech Ring -->
                            <div class="absolute inset-2 border border-dashed border-primary/20 rounded-full animate-[spin_20s_linear_infinite]"></div>
                            <!-- Middle Pulse Ring -->
                            <div class="absolute inset-4 border border-primary/10 rounded-full animate-[pulse_4s_ease-in-out_infinite]"></div>
                            <!-- Inner Data Ring -->
                            <div class="absolute inset-0 border-2 border-dotted border-primary/10 rounded-full animate-[spin-reverse_15s_linear_infinite]"></div>
                        </div>
                        
                        <!-- Core Level Chip -->
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full border border-primary/20 bg-card/40 flex flex-col items-center justify-center shadow-[0_0_50px_-12px_rgba(var(--primary),0.2)] relative z-10 backdrop-blur-2xl group-hover:border-primary/40 transition-colors duration-500">
                            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-primary/5 to-transparent"></div>
                            <span class="text-[8px] uppercase font-black tracking-[0.2em] text-primary/60 mb-0.5">Level</span>
                            <span class="text-xl sm:text-2xl font-black font-mono tracking-tighter leading-none premium-gradient-text">{{ animatedLevel }}</span>
                            <div class="mt-1 flex items-center justify-center relative">
                                <div class="absolute inset-0 blur-lg bg-primary/20 rounded-full animate-pulse"></div>
                                <Award class="w-3 h-3 text-primary relative z-10" />
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 md:w-64 space-y-3">
                        <div class="flex justify-between items-end">
                            <div class="space-y-0.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground">Energy Levels</p>
                                <p class="text-xs font-bold">{{ animatedXP.toLocaleString() }} <span class="text-muted-foreground font-medium">/ {{ maxXPForLevel.toLocaleString() }} XP</span></p>
                            </div>
                            <div class="flex items-center gap-1 text-[10px] font-bold text-primary">
                                <Zap class="w-3 h-3 fill-current" />
                                <span>{{ Math.round(xpPercentage) }}%</span>
                            </div>
                        </div>
                        
                        <div class="relative h-3 w-full bg-muted/30 rounded-full overflow-hidden border border-border/20">
                            <!-- Inner Glow -->
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent pointer-events-none"></div>
                            
                            <div class="h-full bg-primary transition-all duration-1000 ease-out relative shadow-[0_0_15px_rgba(var(--primary),0.5)]" 
                                :style="{ width: `${xpPercentage}%` }">
                                <!-- Ghost Shimmer -->
                                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full animate-[shimmer_3s_infinite]"></div>
                                <!-- Pulsing tip -->
                                <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-r from-transparent to-white/30 animate-pulse"></div>
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
