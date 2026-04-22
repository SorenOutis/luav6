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
    
    // 3D Tilt calculation
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -6; // Slightly more tilt
    const rotateY = ((x - centerX) / centerX) * 6;

    gsap.to(card, {
        rotateX: rotateX,
        rotateY: rotateY,
        transformPerspective: 1000,
        duration: 0.4,
        ease: 'power2.out'
    });

    // Move internal elements for depth
    gsap.to('.unit-card', {
        z: 20,
        duration: 0.4,
        ease: 'power2.out'
    });

    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const handleMouseLeave = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        rotateX: 0,
        rotateY: 0,
        duration: 0.8,
        ease: 'elastic.out(1, 0.3)'
    });

    gsap.to('.unit-card', {
        z: 0,
        duration: 0.8,
        ease: 'elastic.out(1, 0.3)'
    });
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
        y: 60,
        opacity: 0,
        scale: 0.95,
        duration: 1.5,
        ease: 'expo.out'
    });

    // Animate the progress bar fill on scroll
    gsap.from('.progress-fill', {
        scrollTrigger: {
            trigger: '.season-card',
            start: 'top 85%',
        },
        width: '0%',
        duration: 2.5,
        ease: 'expo.inOut'
    });

    // Staggered entrance for countdown units
    gsap.from('.unit-card', {
        scrollTrigger: {
            trigger: '.season-card',
            start: 'top 80%',
        },
        y: 20,
        opacity: 0,
        stagger: 0.1,
        duration: 1,
        ease: 'power3.out'
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
        class="reveal-section mt-16 lg:mt-24 relative group/season season-card preserve-3d"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
    >
        <div class="relative overflow-hidden rounded-xl border border-border/30 dark:border-border/15 bg-card/30 dark:bg-background/30 p-5 sm:p-8 lg:p-10 backdrop-blur-3xl shadow-xl transition-colors duration-500 hover:border-primary/20">
            
            <!-- Dynamic Mouse Glow -->
            <div class="absolute inset-0 opacity-0 group-hover/season:opacity-100 transition-opacity duration-700 pointer-events-none"
                :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--color-primary), transparent 85%)` }">
            </div>

            <!-- Structural Accents -->
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
            <div class="absolute top-0 left-0 w-6 h-6 border-t border-l border-primary/20 pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-6 h-6 border-b border-r border-primary/20 pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 lg:gap-10">
                <div class="space-y-3 sm:space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-8 w-8 items-center justify-center rounded-lg bg-primary/5 border border-primary/10">
                            <Timer class="h-4 w-4 text-primary animate-pulse" />
                        </div>
                        <span class="text-[9px] font-black uppercase tracking-[0.3em] text-primary/70">Temporal Sync</span>
                    </div>
                    
                    <div>
                        <h2 class="text-2xl sm:text-3xl lg:text-5xl font-black uppercase tracking-tighter leading-none italic">
                            {{ activeSeason.name }}
                        </h2>
                        <p class="mt-2 text-[9px] font-bold text-muted-foreground/50 uppercase tracking-[0.2em]">Season Deadline Protocol</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-4 gap-2 sm:gap-3 lg:gap-4 preserve-3d">
                    <div v-for="(unit, key) in { DAYS: countdown.days, HRS: countdown.hours, MIN: countdown.minutes, SEC: countdown.seconds }" 
                        :key="key" 
                        class="unit-card group/unit relative flex flex-col items-center p-3 sm:p-4 lg:p-5 border border-border/20 dark:border-border/10 bg-muted/10 dark:bg-foreground/[0.02] rounded-lg backdrop-blur-md transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.05] hover:border-primary/30 preserve-3d"
                    >
                        <span class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tighter tabular-nums font-mono leading-none text-foreground group-hover/unit:text-primary transition-colors translate-z-10">
                            {{ String(unit).padStart(2, '0') }}
                        </span>
                        <span class="text-[6px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground/60 mt-2 translate-z-10">{{ key }}</span>
                    </div>
                </div>
            </div>
            
            <div v-if="activeSeason.startDate" class="mt-8 lg:mt-10 relative z-10">
                <div class="flex items-end justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <Zap class="h-2.5 w-2.5 text-primary/60" />
                        <span class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground/80">Progress</span>
                    </div>
                    <span class="text-lg font-black font-mono leading-none">{{ progress }}<span class="text-[8px] ml-0.5 text-primary">%</span></span>
                </div>
                
                <div class="relative h-1.5 overflow-hidden rounded-full bg-muted/30 dark:bg-foreground/[0.03] border border-border/5">
                    <div class="progress-fill absolute inset-y-0 left-0 rounded-full bg-primary/60 transition-all duration-1000 shadow-[0_0_10px_rgba(var(--color-primary),0.3)]" 
                        :style="{ width: progress + '%' }">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent w-20 -translate-x-full animate-[scan-horizontal_2s_linear_infinite]"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.season-card {
    --color-primary: var(--color-primary);
}
.translate-z-10 {
    transform: translateZ(10px);
}
</style>

