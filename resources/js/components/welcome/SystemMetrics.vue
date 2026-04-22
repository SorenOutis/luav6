<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Cpu, ClipboardCheck, FileText, Trophy } from 'lucide-vue-next';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import gsap from 'gsap';

const props = defineProps<{
    totalUsers: number;
    totalExams: number;
    totalAssignments: number;
    totalSubmissions: number;
}>();

const animUsers = useNumberAnimation(() => props.totalUsers, 2, 'expo.out');
const animExams = useNumberAnimation(() => props.totalExams, 1.8, 'power2.out');
const animAssignments = useNumberAnimation(() => props.totalAssignments, 2.2, 'expo.out');
const animSubmissions = useNumberAnimation(() => props.totalSubmissions, 2.5, 'power4.out');

const systemStats = computed(() => [
    { label: 'Active Users', value: animUsers.value, unit: 'LEARNERS', icon: Cpu, status: 'NOMINAL', color: 'primary' },
    { label: 'Assessments', value: animExams.value, unit: 'READY', icon: ClipboardCheck, status: 'STABLE', color: 'emerald' },
    { label: 'Assignments', value: animAssignments.value, unit: 'ACTIVE', icon: FileText, status: 'SYNCED', color: 'primary' },
    { label: 'Submissions', value: animSubmissions.value, unit: 'TOTAL', icon: Trophy, status: 'UPDATING', color: 'primary' },
]);

const handleMetricHover = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        y: -12,
        scale: 1.02,
        duration: 0.4,
        ease: 'power2.out'
    });
};

const resetMetricHover = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        y: 0,
        scale: 1,
        duration: 0.6,
        ease: 'power3.out'
    });
};

onMounted(() => {
    // Revamped Metrics Ticker Entrance
    gsap.to('.metric-card', {
        scrollTrigger: {
            trigger: '.metric-card',
            start: 'top 85%',
        },
        y: 0,
        opacity: 1,
        scale: 1,
        stagger: 0.1,
        duration: 1.2,
        ease: 'expo.out'
    });

    // Continuous Scanning Animation for Metrics
    gsap.to('.metric-scan', {
        y: 180, 
        opacity: 1,
        duration: 3,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
        stagger: {
            each: 0.8,
            from: 'random'
        }
    });

    // Micro-bar Pulse Animation
    gsap.to('.metric-bar', {
        scaleY: 1.5,
        opacity: 0.8,
        duration: (i) => 1 + (i % 5) * 0.2,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
        stagger: {
            each: 0.1,
            from: 'start'
        }
    });
});
</script>

<template>
    <div class="reveal-section mt-24 lg:mt-40 grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 lg:gap-10 py-10 lg:py-16 relative">
        <div 
            v-for="(stat, i) in systemStats" 
            :key="stat.label" 
            @mouseenter="handleMetricHover($event)"
            @mouseleave="resetMetricHover($event)"
            class="metric-card group relative flex flex-col p-6 lg:p-8 rounded-xl border border-border/20 bg-card/40 backdrop-blur-md overflow-hidden opacity-0 translate-y-12 scale-95"
        >
            <div class="absolute top-0 left-0 w-2 h-2 border-t border-l border-primary/40"></div>
            <div class="absolute bottom-0 right-0 w-2 h-2 border-b border-r border-primary/40"></div>

            <div class="metric-scan absolute inset-x-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent opacity-0 z-10"></div>

            <div class="flex items-center justify-between mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/5 border border-primary/10 group-hover:bg-primary/10 transition-colors">
                    <component :is="stat.icon" class="h-5 w-5 text-primary opacity-70 group-hover:opacity-100 transition-opacity" />
                </div>
                <span class="text-[8px] font-black tracking-widest text-primary/40 tabular-nums font-mono">{{ stat.status }}</span>
            </div>

            <div class="space-y-1">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-muted-foreground/60">{{ stat.label }}</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl lg:text-5xl font-black tracking-tighter tabular-nums leading-none">{{ stat.value }}</span>
                    <span class="text-[9px] lg:text-[10px] font-black text-primary/80 tracking-widest uppercase">{{ stat.unit }}</span>
                </div>
            </div>

            <div class="mt-6 flex items-end gap-1 h-4">
                <div v-for="j in 5" :key="j" 
                     class="flex-1 bg-primary/20 rounded-t-sm metric-bar origin-bottom"
                     :style="{ height: (30 + (Math.sin(i * 2 + j) + 1) * 35) + '%' }">
                </div>
            </div>
        </div>
    </div>
</template>
