<script setup lang="ts">
import gsap from 'gsap';
import { Cpu, ClipboardCheck, FileText, Trophy } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';

const props = defineProps<{
    totalUsers: number;
    totalExams: number;
    totalAssignments: number;
    totalSubmissions: number;
}>();

const animUsers = useNumberAnimation(() => props.totalUsers, 2, 'expo.out');
const animExams = useNumberAnimation(() => props.totalExams, 1.8, 'power2.out');
const animAssignments = useNumberAnimation(
    () => props.totalAssignments,
    2.2,
    'expo.out',
);
const animSubmissions = useNumberAnimation(
    () => props.totalSubmissions,
    2.5,
    'power4.out',
);

const systemStats = computed(() => [
    {
        label: 'Learners Supported',
        value: animUsers.value,
        unit: 'ACTIVE',
        icon: Cpu,
        status: 'ONLINE',
        color: 'primary',
    },
    {
        label: 'Assessments Running',
        value: animExams.value,
        unit: 'LIVE',
        icon: ClipboardCheck,
        status: 'STABLE',
        color: 'emerald',
    },
    {
        label: 'Assignments Tracked',
        value: animAssignments.value,
        unit: 'SYNCED',
        icon: FileText,
        status: 'FLOWING',
        color: 'primary',
    },
    {
        label: 'Feedback Records',
        value: animSubmissions.value,
        unit: 'TOTAL',
        icon: Trophy,
        status: 'UPDATING',
        color: 'primary',
    },
]);

const handleMetricHover = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        y: -12,
        scale: 1.02,
        duration: 0.4,
        ease: 'power2.out',
    });
};

const resetMetricHover = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        y: 0,
        scale: 1,
        duration: 0.6,
        ease: 'power3.out',
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
        ease: 'expo.out',
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
            from: 'random',
        },
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
            from: 'start',
        },
    });
});
</script>

<template>
    <div
        class="reveal-section relative mt-24 grid grid-cols-2 gap-4 py-10 sm:gap-6 md:grid-cols-4 lg:mt-40 lg:gap-10 lg:py-16"
    >
        <div
            v-for="(stat, i) in systemStats"
            :key="stat.label"
            @mouseenter="handleMetricHover($event)"
            @mouseleave="resetMetricHover($event)"
            class="metric-card group relative flex translate-y-12 scale-95 flex-col overflow-hidden rounded-xl border border-border/20 bg-card/40 p-6 opacity-0 backdrop-blur-md lg:p-8"
        >
            <div
                class="absolute top-0 left-0 h-2 w-2 border-t border-l border-primary/40"
            ></div>
            <div
                class="absolute right-0 bottom-0 h-2 w-2 border-r border-b border-primary/40"
            ></div>

            <div
                class="metric-scan absolute inset-x-0 z-10 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent opacity-0"
            ></div>

            <div class="mb-4 flex items-center justify-between">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-primary/10 bg-primary/5 transition-colors group-hover:bg-primary/10"
                >
                    <component
                        :is="stat.icon"
                        class="h-5 w-5 text-primary opacity-70 transition-opacity group-hover:opacity-100"
                    />
                </div>
                <span
                    class="font-mono text-[8px] font-black tracking-widest text-primary/40 tabular-nums"
                    >{{ stat.status }}</span
                >
            </div>

            <div class="space-y-1">
                <p
                    class="text-[9px] font-black tracking-[0.25em] text-muted-foreground/60 uppercase"
                >
                    {{ stat.label }}
                </p>
                <div class="flex items-baseline gap-2">
                    <span
                        class="text-3xl leading-none font-black tracking-tighter tabular-nums lg:text-5xl"
                        >{{ stat.value }}</span
                    >
                    <span
                        class="text-[9px] font-black tracking-widest text-primary/80 uppercase lg:text-[10px]"
                        >{{ stat.unit }}</span
                    >
                </div>
            </div>

            <div class="mt-6 flex h-4 items-end gap-1">
                <div
                    v-for="j in 5"
                    :key="j"
                    class="metric-bar flex-1 origin-bottom rounded-t-sm bg-primary/20"
                    :style="{
                        height: 30 + (Math.sin(i * 2 + j) + 1) * 35 + '%',
                    }"
                ></div>
            </div>
        </div>
    </div>
</template>
