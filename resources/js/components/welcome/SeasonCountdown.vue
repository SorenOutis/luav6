<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Timer, Zap } from 'lucide-vue-next';
import gsap from 'gsap';

const props = defineProps<{
    activeSeason: {
        name: string;
        startDate: string | null;
        endDate: string | null;
        showCountdown: boolean;
    } | null;
}>();

const countdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
const countdownActive = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const progress = computed(() => {
    if (!props.activeSeason?.startDate || !props.activeSeason?.endDate) return 0;
    const start = new Date(props.activeSeason.startDate).getTime();
    const end = new Date(props.activeSeason.endDate).getTime();
    const now = Date.now();
    return Math.min(100, Math.max(0, Math.round(((now - start) / (end - start)) * 100)));
});

const updateCountdown = () => {
    if (!props.activeSeason?.endDate || !props.activeSeason?.showCountdown) {
        countdownActive.value = false;
        return;
    }
    const end = new Date(props.activeSeason.endDate).getTime();
    const now = Date.now();
    const diff = end - now;
    if (diff <= 0) {
        countdownActive.value = false;
        countdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
        if (countdownInterval) clearInterval(countdownInterval);
        return;
    }
    countdownActive.value = true;
    countdown.value = {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((diff / (1000 * 60)) % 60),
        seconds: Math.floor((diff / 1000) % 60),
    };
};

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

onMounted(() => {
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);

    // Entrance Animation
    gsap.from('.season-card', {
        scrollTrigger: {
            trigger: '.season-card',
            start: 'top 90%',
        },
        y: 40,
        opacity: 0,
        duration: 1.2,
        ease: 'power4.out'
    });

    // Animate the progress bar fill on scroll
    gsap.from('.progress-fill', {
        scrollTrigger: {
            trigger: '.season-card',
            start: 'top 85%',
        },
        width: '0%',
        duration: 2,
        ease: 'expo.out'
    });
});

onBeforeUnmount(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});
</script>

<template>
    <div v-if="countdownActive && activeSeason" 
        class="reveal-section mt-24 lg:mt-40 relative group/season season-card"
        @mousemove="handleMouseMove"
    >
        <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/40 dark:bg-background/40 p-6 sm:p-10 lg:p-14 backdrop-blur-2xl shadow-2xl transition-all duration-500 hover:border-primary/30">
            
            <!-- Dynamic Mouse Glow -->
            <div class="absolute inset-0 opacity-0 group-hover/season:opacity-100 transition-opacity duration-700 pointer-events-none"
                :style="{ background: `radial-gradient(800px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--color-primary), transparent 80%)` }">
            </div>
            <div class="absolute inset-0 bg-background/80 dark:bg-background/90 opacity-0 group-hover/season:opacity-40 transition-opacity duration-700 pointer-events-none"></div>

            <!-- Structural Accents -->
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
            <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-primary/40 pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-primary/40 pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8 lg:gap-12">
                <div class="space-y-4 sm:space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 border border-primary/20">
                            <Timer class="h-5 w-5 text-primary animate-pulse" />
                            <div class="absolute inset-0 rounded-lg bg-primary/20 animate-ping opacity-20"></div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">System Clock</span>
                            <span class="text-[8px] font-bold text-muted-foreground/60 uppercase tracking-widest">Protocol_Active</span>
                        </div>
                    </div>
                    
                    <div>
                        <h2 class="text-3xl sm:text-4xl lg:text-6xl font-black uppercase tracking-tighter leading-none italic group-hover/season:translate-x-2 transition-transform duration-500">
                            {{ activeSeason.name }}
                        </h2>
                        <div class="mt-4 flex items-center gap-3">
                            <div class="h-px w-8 bg-primary/40"></div>
                            <p class="text-[10px] sm:text-xs font-bold text-muted-foreground uppercase tracking-[0.2em]">Temporal Deadline Synchronization</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
                    <div v-for="(unit, key) in { DAYS: countdown.days, HRS: countdown.hours, MIN: countdown.minutes, SEC: countdown.seconds }" 
                        :key="key" 
                        class="group/unit relative flex flex-col items-center p-4 sm:p-6 lg:p-8 border border-border/40 dark:border-border/15 bg-muted/20 dark:bg-foreground/[0.03] rounded-xl backdrop-blur-md transition-all hover:scale-105 hover:bg-muted/40 dark:hover:bg-foreground/[0.06] hover:border-primary/40"
                    >
                        <div class="absolute -top-1.5 -right-1.5 h-3 w-3 border-t border-r border-primary/40 opacity-0 group-hover/unit:opacity-100 transition-opacity"></div>
                        <div class="absolute -bottom-1.5 -left-1.5 h-3 w-3 border-b border-l border-primary/40 opacity-0 group-hover/unit:opacity-100 transition-opacity"></div>
                        
                        <span class="text-3xl sm:text-4xl lg:text-6xl font-black tracking-tighter tabular-nums font-mono leading-none text-foreground group-hover/unit:text-primary transition-colors">
                            {{ String(unit).padStart(2, '0') }}
                        </span>
                        <span class="text-[7px] sm:text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground mt-3 group-hover/unit:text-foreground transition-colors">{{ key }}</span>
                    </div>
                </div>
            </div>
            
            <div v-if="activeSeason.startDate" class="mt-12 lg:mt-16 relative z-10">
                <div class="flex items-end justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <Zap class="h-3 w-3 text-primary" />
                        <span class="text-[10px] font-black uppercase tracking-[0.3em] text-foreground">Mission Progress</span>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-black font-mono leading-none">{{ progress }}</span>
                        <span class="text-[10px] font-black text-primary">%</span>
                    </div>
                </div>
                
                <div class="relative h-2.5 overflow-hidden rounded-full bg-muted/40 dark:bg-foreground/5 border border-border/10">
                    <div class="progress-fill absolute inset-y-0 left-0 rounded-full bg-primary/80 transition-all duration-1000 shadow-[0_0_15px_rgba(var(--color-primary),0.5)]" 
                        :style="{ width: progress + '%' }">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent w-24 -translate-x-full animate-[scan-horizontal_2s_linear_infinite]"></div>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-between items-center">
                    <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/40">Node_Origin: {{ new Date(activeSeason.startDate).toLocaleDateString() }}</span>
                    <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/40">Node_Terminal: {{ new Date(activeSeason.endDate!).toLocaleDateString() }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.season-card {
    --color-primary: var(--color-primary);
}
</style>

