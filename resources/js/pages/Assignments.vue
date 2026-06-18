<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    FileUp,
    CheckCircle2,
    Clock,
    AlertCircle,
    FileText,
    Download,
    TrendingUp,
    Calendar,
    BookOpen,
    Sparkles,
    ShieldCheck,
    Cpu,
} from 'lucide-vue-next';
import { onMounted, ref, computed, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/card/Card.vue';
import type { BreadcrumbItem } from '@/types';

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);

gsap.registerPlugin(ScrollTrigger);
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

interface Assignment {
    id: number;
    title: string;
    description: string;
    due_date: string;
    course: {
        id: number;
        name: string;
    } | null;
    submission: {
        submitted: boolean;
        status: string;
        grade: string | null;
        file_path: string | null;
        submitted_at: string | null;
    } | null;
}

const props = defineProps<{
    assignments: Assignment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Assignments', href: '/assignments' },
];

const container = ref<HTMLElement | null>(null);
const selectedAssignment = ref<Assignment | null>(null);
const selectedAssignmentId = ref<number | string>('');
const showUploadModal = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const activeTab = ref<'pending' | 'completed'>('pending');
const selectedMonth = ref<string>('all');

const months = [
    { value: 'all', label: 'All Time' },
    { value: '0', label: 'January' },
    { value: '1', label: 'February' },
    { value: '2', label: 'March' },
    { value: '3', label: 'April' },
    { value: '4', label: 'May' },
    { value: '5', label: 'June' },
    { value: '6', label: 'July' },
    { value: '7', label: 'August' },
    { value: '8', label: 'September' },
    { value: '9', label: 'October' },
    { value: '10', label: 'November' },
    { value: '11', label: 'December' },
];

const filteredAssignments = computed(() => {
    let list = props.assignments;

    // Filter by tab
    if (activeTab.value === 'pending') {
        list = list.filter((a) => !a.submission?.submitted);
    } else {
        list = list.filter((a) => a.submission?.submitted);
    }

    // Filter by month
    if (selectedMonth.value !== 'all') {
        const monthIndex = parseInt(selectedMonth.value);
        list = list.filter((a) => {
            const dateStr = a.submission?.submitted_at || a.due_date;
            if (!dateStr) return false;
            return new Date(dateStr).getMonth() === monthIndex;
        });
    }

    return list;
});

const form = useForm({
    file: null as File | null,
});

const closeUploadModal = () => {
    const modal = document.querySelector('.modal-content');
    const overlay = document.querySelector('.modal-overlay');

    if (modal && overlay) {
        gsap.to(modal, {
            scale: 0.9,
            opacity: 0,
            y: 20,
            duration: 0.4,
            ease: 'power2.in',
            onComplete: () => {
                showUploadModal.value = false;
                form.reset();
                selectedAssignment.value = null;
                selectedAssignmentId.value = '';
            },
        });
        gsap.to(overlay, {
            opacity: 0,
            duration: 0.4,
            ease: 'power2.in',
        });
    } else {
        showUploadModal.value = false;
        form.reset();
        selectedAssignment.value = null;
        selectedAssignmentId.value = '';
    }
};

const onModalEnter = (el: Element) => {
    const modal = el.querySelector('.modal-content');
    const overlay = el.querySelector('.modal-overlay');

    gsap.set(modal, { scale: 0.9, opacity: 0, y: 20 });
    gsap.set(overlay, { opacity: 0 });

    gsap.to(overlay, {
        opacity: 1,
        duration: 0.5,
        ease: 'power3.out',
    });

    gsap.to(modal, {
        scale: 1,
        opacity: 1,
        y: 0,
        duration: 0.6,
        ease: 'back.out(1.7)',
        delay: 0.1,
    });
};

const submitAssignment = () => {
    if (!form.file || !selectedAssignmentId.value) return;

    form.post(route('assignments.submit', selectedAssignmentId.value), {
        onSuccess: () => {
            closeUploadModal();
        },
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Submitted':
            return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'Graded':
            return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'Pending':
            return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        default:
            return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
};

const isOverdue = (dueDate: string) => {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
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
    if (!container.value) return;

    // Sync isBooted with global loader
    if (!isLoaderVisible.value) {
        isBooted.value = true;
    }

    watch(
        isLoaderVisible,
        (visible) => {
            if (!visible) {
                isBooted.value = true;
            }
        },
        { immediate: true },
    );

    const orbs = container.value.querySelectorAll('.orb');
    orbs.forEach((orb, i) => {
        gsap.to(orb, {
            x: `random(-40, 40)`,
            y: `random(-40, 40)`,
            duration: 10 + i * 5,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
    });
});
</script>

<script lang="ts">
// Helper for Ziggy route if not globally available
declare const route: any;
</script>

<template>
    <Head title="Assignments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="container"
            class="relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 perspective-[1000px] md:p-10"
        >
            <!-- Decorative Orbs -->
            <div
                class="orb pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>
            <div
                class="orb pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>

            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{
                    duration: 1,
                    ease: [0.16, 1, 0.3, 1],
                    delay: 0.1,
                }"
                class="assignments-hero header-content group/hero relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-end"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-[2px] w-8 rounded-full bg-primary/40 transition-all duration-500 group-hover/hero:w-12"
                        ></div>
                        <h1
                            class="text-2xl font-black tracking-tighter uppercase"
                        >
                            Mission_Briefings
                        </h1>
                    </div>
                    <p
                        class="border-l-2 border-primary/10 pl-11 text-sm text-[9px] font-medium tracking-widest text-muted-foreground uppercase transition-colors group-hover/hero:border-primary/30"
                    >
                        Complete your objectives to earn XP and advance your
                        rank.
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Global Submit Button -->
                    <Button
                        @click="
                            selectedAssignment = null;
                            selectedAssignmentId = '';
                            showUploadModal = true;
                        "
                        variant="default"
                        size="sm"
                        class="group/btn h-10 gap-2 rounded-xl border border-primary/20 bg-primary px-6 text-[10px] font-black tracking-[0.2em] text-primary-foreground uppercase shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] transition-all duration-500 hover:shadow-[0_0_30px_rgba(var(--primary-rgb),0.5)]"
                    >
                        <FileUp
                            class="h-4 w-4 transition-transform group-hover/btn:-translate-y-0.5"
                        />
                        SUBMIT_INTEL
                    </Button>

                    <!-- Month Filter -->
                    <div
                        class="group/filter flex h-10 items-center gap-3 rounded-2xl border border-border/50 bg-muted/30 px-4 py-2"
                    >
                        <Calendar
                            class="h-3.5 w-3.5 text-primary/60 transition-colors group-hover/filter:text-primary"
                        />
                        <select
                            v-model="selectedMonth"
                            class="h-full cursor-pointer appearance-none border-none bg-transparent pr-6 font-mono text-[10px] font-black tracking-widest uppercase focus:ring-0"
                        >
                            <option
                                v-for="month in months"
                                :key="month.value"
                                :value="month.value"
                                class="bg-[#0a0a0a] text-foreground"
                            >
                                {{ month.label.toUpperCase() }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="flex h-10 items-center gap-2 rounded-full border border-primary/10 bg-primary/5 px-4 py-1.5 font-mono"
                    >
                        <TrendingUp class="h-3.5 w-3.5 text-primary" />
                        <span
                            class="text-[9px] font-black tracking-widest uppercase"
                            >RANK:VANGUARD</span
                        >
                    </div>
                </div>
            </Motion>

            <!-- Stats Overview -->
            <div class="z-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                <Motion
                    v-for="(stat, sIdx) in [
                        {
                            label: 'ACTIVE_OBJECTIVES',
                            value: assignments.filter(
                                (a) => !a.submission?.submitted,
                            ).length,
                            sub: 'IMMEDIATE_PRIORITY',
                            icon: Clock,
                            glowColor: 'orange' as const,
                        },
                        {
                            label: 'COMPLETED_MISSIONS',
                            value: assignments.filter(
                                (a) => a.submission?.submitted,
                            ).length,
                            sub: 'OBJECTIVES_ACHIEVED',
                            icon: CheckCircle2,
                            glowColor: 'green' as const,
                        },
                        {
                            label: 'PERFORMANCE_RANK',
                            value: 'A+',
                            sub: 'TOP_1%_OF_BATTALION',
                            icon: Sparkles,
                            glowColor: 'purple' as const,
                        },
                    ]"
                    :key="sIdx"
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{
                        duration: 0.8,
                        ease: [0.16, 1, 0.3, 1],
                        delay: 0.2 + sIdx * 0.1,
                    }"
                >
                    <SpotlightCard
                        customSize
                        :glowColor="stat.glowColor"
                        :spotlightSize="350"
                        className="stats-card p-5 relative group/stat premium-hover bg-card/40 flex flex-col justify-between"
                        @mousemove="handleMouseMove"
                    >
                        <!-- Inner container to clip overflowing background icons without clipping the outer glow -->
                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                        >
                            <!-- Persistent colored corner highlights -->
                            <div
                                class="absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-40 blur-3xl transition-opacity duration-700 group-hover/stat:opacity-70"
                                :class="{
                                    'bg-orange-500/30':
                                        stat.glowColor === 'orange',
                                    'bg-emerald-500/30':
                                        stat.glowColor === 'green',
                                    'bg-purple-500/30':
                                        stat.glowColor === 'purple',
                                }"
                            ></div>
                            <div
                                class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full opacity-25 blur-3xl transition-opacity duration-700 group-hover/stat:opacity-50"
                                :class="{
                                    'bg-orange-400/25':
                                        stat.glowColor === 'orange',
                                    'bg-emerald-400/25':
                                        stat.glowColor === 'green',
                                    'bg-purple-400/25':
                                        stat.glowColor === 'purple',
                                }"
                            ></div>

                            <!-- Tech Grid Background -->
                            <div
                                class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/stat:opacity-[0.05]"
                            >
                                <svg
                                    class="h-full w-full"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="100%"
                                    height="100%"
                                >
                                    <defs>
                                        <pattern
                                            :id="`stat-grid-${sIdx}`"
                                            width="15"
                                            height="15"
                                            patternUnits="userSpaceOnUse"
                                        >
                                            <path
                                                d="M 15 0 L 0 0 0 15"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="0.5"
                                            />
                                        </pattern>
                                    </defs>
                                    <rect
                                        width="100%"
                                        height="100%"
                                        :fill="`url(#stat-grid-${sIdx})`"
                                    />
                                </svg>
                            </div>

                            <!-- Tech Scanning Line -->
                            <div
                                class="group-hover/stat:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/stat:opacity-100"
                            ></div>

                            <!-- Hover Bloom Effect -->
                            <div
                                class="absolute inset-0 opacity-0 transition-opacity duration-700 group-hover/stat:opacity-100"
                                :style="{
                                    background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.08), transparent 40%)`,
                                }"
                            ></div>

                            <!-- Corner Accents -->
                            <div
                                class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/stat:opacity-100"
                            ></div>
                            <div
                                class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/stat:opacity-100"
                            ></div>

                            <!-- Silhouette Background Icon -->
                            <div
                                class="absolute -top-2 -right-2 opacity-[0.03] transition-all duration-700 group-hover/stat:scale-110 group-hover/stat:rotate-[20deg] group-hover/stat:opacity-[0.06] sm:-top-3 sm:-right-3"
                            >
                                <component
                                    :is="stat.icon"
                                    class="h-16 w-16 sm:h-20 sm:w-20"
                                />
                            </div>
                        </div>

                        <div
                            class="relative z-10 flex h-full w-full flex-col justify-between"
                        >
                            <div>
                                <p
                                    class="font-mono text-[8px] font-black tracking-[0.3em] text-muted-foreground/60 uppercase"
                                >
                                    >_{{ stat.label }}
                                </p>
                                <h3
                                    class="mt-1 font-mono text-3xl font-black tracking-tighter text-foreground transition-colors group-hover/stat:text-primary"
                                >
                                    {{ stat.value }}
                                </h3>
                            </div>
                            <div
                                class="mt-4 flex items-center gap-2 border-t border-border/10 pt-4"
                            >
                                <div
                                    class="h-1 w-1 animate-pulse rounded-full bg-primary/40"
                                ></div>
                                <span
                                    class="font-mono text-[8px] font-black tracking-[0.2em] text-muted-foreground/40 uppercase"
                                    >{{ stat.sub }}</span
                                >
                            </div>
                        </div>
                    </SpotlightCard>
                </Motion>
            </div>

            <!-- Tabs Navigation -->
            <Motion
                :initial="{ opacity: 0, y: 10 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{ duration: 0.8, delay: 0.5 }"
                class="tabs-nav z-10 flex border-b border-border/10"
            >
                <button
                    @click="activeTab = 'pending'"
                    :class="[
                        'relative overflow-hidden px-8 py-4 text-[10px] font-black tracking-[0.2em] uppercase transition-all',
                        activeTab === 'pending'
                            ? 'text-primary'
                            : 'text-muted-foreground/40 hover:text-muted-foreground',
                    ]"
                >
                    Pending
                    <div
                        v-if="activeTab === 'pending'"
                        class="animate-in slide-in-from-left absolute right-0 bottom-0 left-0 h-0.5 bg-primary duration-300"
                    ></div>
                </button>
                <button
                    @click="activeTab = 'completed'"
                    :class="[
                        'relative overflow-hidden px-8 py-4 text-[10px] font-black tracking-[0.2em] uppercase transition-all',
                        activeTab === 'completed'
                            ? 'text-primary'
                            : 'text-muted-foreground/40 hover:text-muted-foreground',
                    ]"
                >
                    Completed
                    <div
                        v-if="activeTab === 'completed'"
                        class="animate-in slide-in-from-left absolute right-0 bottom-0 left-0 h-0.5 bg-primary duration-300"
                    ></div>
                </button>
            </Motion>

            <!-- Assignments Grid -->
            <div class="z-10 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <TransitionGroup
                    enter-active-class="animate-in fade-in slide-in-from-bottom-4 duration-500"
                    leave-active-class="animate-out fade-out slide-out-to-top-4 duration-300 absolute"
                >
                    <Motion
                        v-for="(assignment, aIdx) in filteredAssignments"
                        :key="assignment.id"
                        :initial="{ opacity: 0, y: 40 }"
                        :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                        :in-view-options="{ once: true, margin: '-50px' }"
                        :transition="{
                            duration: 1,
                            ease: [0.16, 1, 0.3, 1],
                            delay: aIdx * 0.05,
                        }"
                        class="assignment-card surface-card group/card premium-hover relative overflow-hidden p-5 transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-primary/10 md:p-6"
                        @mousemove="handleMouseMove"
                    >
                        <!-- Tech Grid Background -->
                        <div
                            class="pointer-events-none absolute inset-0 opacity-[0.03] transition-opacity group-hover/card:opacity-[0.05]"
                        >
                            <svg
                                class="h-full w-full"
                                xmlns="http://www.w3.org/2000/svg"
                                width="100%"
                                height="100%"
                            >
                                <defs>
                                    <pattern
                                        :id="`assignment-grid-${assignment.id}`"
                                        width="15"
                                        height="15"
                                        patternUnits="userSpaceOnUse"
                                    >
                                        <path
                                            d="M 15 0 L 0 0 0 15"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="0.5"
                                        />
                                    </pattern>
                                </defs>
                                <rect
                                    width="100%"
                                    height="100%"
                                    :fill="`url(#assignment-grid-${assignment.id})`"
                                />
                            </svg>
                        </div>

                        <!-- Tech Scanning Line -->
                        <div
                            class="group-hover/card:animate-scan-horizontal pointer-events-none absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/10 to-transparent opacity-0 transition-opacity group-hover/card:opacity-100"
                        ></div>

                        <!-- Hover Bloom Effect -->
                        <div
                            class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-700 group-hover/card:opacity-100"
                            :style="{
                                background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.1), transparent 40%)`,
                            }"
                        ></div>

                        <!-- Silhouette Background Icon -->
                        <div
                            class="pointer-events-none absolute -right-6 -bottom-6 scale-110 rotate-12 opacity-[0.03] transition-all duration-700 group-hover:rotate-0 group-hover/card:opacity-[0.08]"
                        >
                            <BookOpen class="h-32 w-32" />
                        </div>

                        <div class="relative z-10 flex h-full flex-col">
                            <div class="mb-4 flex items-start justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="rounded border border-primary/20 bg-primary/10 px-2 py-0.5 font-mono text-[8px] font-black tracking-widest text-primary uppercase"
                                        >
                                            {{
                                                assignment.course?.name?.toUpperCase() ||
                                                'GENERAL_UNIT'
                                            }}
                                        </div>
                                        <div
                                            :class="[
                                                'rounded border px-2 py-0.5 font-mono text-[8px] font-black tracking-widest uppercase',
                                                getStatusColor(
                                                    assignment.submission
                                                        ?.status || 'Pending',
                                                )
                                                    .split(' ')
                                                    .filter(
                                                        (c) =>
                                                            !c.includes('bg-'),
                                                    )
                                                    .join(' '),
                                                getStatusColor(
                                                    assignment.submission
                                                        ?.status || 'Pending',
                                                )
                                                    .split(' ')
                                                    .find((c) =>
                                                        c.includes('bg-'),
                                                    )
                                                    ?.replace('/10', '/20'),
                                            ]"
                                        >
                                            {{
                                                assignment.submission?.status?.toUpperCase() ||
                                                'PENDING_OPS'
                                            }}
                                        </div>
                                    </div>
                                    <h3
                                        class="text-xl leading-tight font-black tracking-tighter uppercase transition-colors duration-500 group-hover/card:text-primary"
                                    >
                                        {{ assignment.title }}
                                    </h3>
                                </div>

                                <div class="text-right">
                                    <p
                                        class="mb-1 font-mono text-[8px] font-black tracking-[0.2em] text-muted-foreground/40 uppercase"
                                    >
                                        {{
                                            assignment.submission?.submitted
                                                ? '>_TRANSMISSION'
                                                : '>_DEADLINE'
                                        }}
                                    </p>
                                    <p
                                        class="font-mono text-xs font-black tracking-tight"
                                        :class="
                                            isOverdue(assignment.due_date) &&
                                            !assignment.submission?.submitted
                                                ? 'text-red-500'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            assignment.submission?.submitted_at
                                                ? new Date(
                                                      assignment.submission
                                                          .submitted_at,
                                                  )
                                                      .toLocaleDateString()
                                                      .toUpperCase()
                                                : assignment.due_date
                                                  ? new Date(
                                                        assignment.due_date,
                                                    )
                                                        .toLocaleDateString()
                                                        .toUpperCase()
                                                  : 'UNDEFINED'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                class="mb-4 line-clamp-2 flex-grow text-xs leading-relaxed font-medium text-muted-foreground/70"
                            >
                                {{
                                    assignment.description ||
                                    'No specialized mission intelligence provided for this objective. Proceed with standard operational procedures.'
                                }}
                            </p>

                            <div
                                class="mt-auto flex items-center justify-between border-t border-border/10 pt-4"
                            >
                                <!-- Enhanced Mission Status -->
                                <div
                                    v-if="assignment.submission?.submitted"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-500/10 bg-emerald-500/5"
                                    >
                                        <FileText
                                            class="h-5 w-5 text-emerald-500"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="font-mono text-[8px] font-black tracking-widest text-muted-foreground/40 uppercase"
                                        >
                                            >_SECURE_DATA
                                        </p>
                                        <p
                                            class="font-mono text-xs font-black text-emerald-500/80"
                                        >
                                            VERIFIED_TRANSMISSION
                                        </p>
                                    </div>
                                </div>

                                <div v-else class="flex items-center gap-2">
                                    <div
                                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"
                                    ></div>
                                    <span
                                        class="font-mono text-[8px] font-black tracking-[0.2em] text-amber-500/80 uppercase"
                                        >>_OBJECTIVE_INCOMPLETE</span
                                    >
                                </div>

                                <div class="flex gap-3">
                                    <Button
                                        v-if="assignment.submission?.submitted"
                                        variant="outline"
                                        size="sm"
                                        class="h-9 gap-2 rounded-xl border-white/5 bg-transparent px-4 font-mono text-[9px] font-black tracking-widest uppercase hover:bg-white/5"
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                        INTEL
                                    </Button>
                                    <Button
                                        v-if="!assignment.submission?.submitted"
                                        @click="
                                            selectedAssignment = assignment;
                                            selectedAssignmentId =
                                                assignment.id;
                                            showUploadModal = true;
                                        "
                                        variant="default"
                                        size="sm"
                                        class="group/btn h-9 gap-2 rounded-xl px-6 text-[9px] font-black tracking-[0.15em] uppercase shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] transition-all duration-500 hover:shadow-[0_0_30px_rgba(var(--primary-rgb),0.5)]"
                                    >
                                        <FileUp
                                            class="h-3.5 w-3.5 transition-transform group-hover/btn:-translate-y-0.5"
                                        />
                                        SUBMIT_INTEL
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Motion>
                </TransitionGroup>
            </div>

            <!-- Empty State -->
            <div
                v-if="filteredAssignments.length === 0"
                class="animate-in fade-in zoom-in z-10 flex flex-col items-center justify-center py-24 duration-700"
            >
                <div class="group/empty relative">
                    <div
                        class="absolute inset-0 rounded-full bg-primary/20 opacity-0 blur-3xl transition-opacity duration-1000 group-hover/empty:opacity-100"
                    ></div>
                    <div
                        class="relative z-10 mb-8 flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl border border-border/10 bg-muted/5 transition-colors duration-500 group-hover/empty:border-primary/30"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 opacity-[0.05]"
                        >
                            <svg
                                class="h-full w-full"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <defs>
                                    <pattern
                                        id="empty-grid"
                                        width="10"
                                        height="10"
                                        patternUnits="userSpaceOnUse"
                                    >
                                        <path
                                            d="M 10 0 L 0 0 0 10"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="0.5"
                                        />
                                    </pattern>
                                </defs>
                                <rect
                                    width="100%"
                                    height="100%"
                                    fill="url(#empty-grid)"
                                />
                            </svg>
                        </div>
                        <BookOpen
                            class="h-10 w-10 text-muted-foreground/20 transition-colors duration-500 group-hover/empty:text-primary/40"
                        />
                    </div>
                </div>
                <h3
                    class="text-2xl font-black tracking-tighter text-muted-foreground/40 uppercase transition-colors duration-500 group-hover/empty:text-foreground"
                >
                    NO_MISSIONS_FOUND
                </h3>
                <p
                    class="mt-3 font-mono text-[10px] font-black tracking-[0.3em] text-muted-foreground/20 uppercase"
                >
                    ADJUST_FILTERS_OR_STANDBY_FOR_OBJECTIVES
                </p>
            </div>

            <!-- Upload Modal -->
            <Transition @enter="onModalEnter" :css="false">
                <div
                    v-if="showUploadModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <div
                        class="modal-overlay absolute inset-0 bg-background/80 backdrop-blur-2xl"
                        @click="closeUploadModal"
                    ></div>

                    <div
                        class="modal-content surface-card relative z-10 w-full max-w-lg overflow-hidden rounded-[2rem] border border-primary/20 bg-gradient-to-b from-primary/10 to-transparent p-0.5 shadow-[0_0_50px_-12px_rgba(var(--primary-rgb),0.3)]"
                    >
                        <!-- Modal Inner Content -->
                        <div
                            class="relative overflow-hidden rounded-[1.9rem] bg-background/40 p-6 backdrop-blur-md"
                        >
                            <!-- Tactical Background UI -->
                            <div
                                class="pointer-events-none absolute inset-0 opacity-20"
                            >
                                <div
                                    class="absolute top-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-primary/50 to-transparent"
                                ></div>
                                <div
                                    class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-primary/50 to-transparent"
                                ></div>
                                <div
                                    class="absolute top-0 left-10 h-full w-px bg-gradient-to-b from-transparent via-primary/20 to-transparent"
                                ></div>
                                <div
                                    class="absolute top-0 right-10 h-full w-px bg-gradient-to-b from-transparent via-primary/20 to-transparent"
                                ></div>
                            </div>

                            <div class="relative z-10">
                                <div
                                    class="mb-6 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 shadow-[0_0_20px_rgba(var(--primary-rgb),0.2)]"
                                        >
                                            <Cpu
                                                class="h-5 w-5 animate-pulse text-primary"
                                            />
                                        </div>
                                        <div>
                                            <p
                                                class="text-[9px] font-black tracking-[0.3em] text-primary/80 uppercase"
                                            >
                                                Transmission Protocol 7-A
                                            </p>
                                            <h2
                                                class="mt-1 text-2xl leading-none font-black tracking-tighter uppercase"
                                            >
                                                Submit Intel
                                            </h2>
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5"
                                    >
                                        <div class="flex items-center gap-2">
                                            <ShieldCheck
                                                class="h-3.5 w-3.5 text-emerald-400"
                                            />
                                            <span
                                                class="text-[9px] font-black tracking-widest text-emerald-400 uppercase"
                                                >Secure</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Selector -->
                                <div
                                    v-if="!selectedAssignment"
                                    class="mb-6 space-y-2"
                                >
                                    <label
                                        class="text-[9px] font-black tracking-[0.2em] text-muted-foreground/50 uppercase"
                                        >Designate Objective</label
                                    >
                                    <div class="group relative">
                                        <select
                                            v-model="selectedAssignmentId"
                                            class="w-full cursor-pointer appearance-none rounded-xl border border-white/10 bg-white/[0.03] px-5 py-3 text-[10px] font-bold tracking-[0.15em] uppercase transition-all outline-none hover:bg-white/[0.05] focus:border-primary/40 focus:ring-2 focus:ring-primary/40"
                                        >
                                            <option value="" disabled>
                                                Awaiting Objective
                                                Designation...
                                            </option>
                                            <option
                                                v-for="a in assignments.filter(
                                                    (x) =>
                                                        !x.submission
                                                            ?.submitted,
                                                )"
                                                :key="a.id"
                                                :value="a.id"
                                                class="bg-[#0a0a0a] text-foreground"
                                            >
                                                {{ a.title }}
                                            </option>
                                        </select>
                                        <div
                                            class="pointer-events-none absolute top-1/2 right-5 -translate-y-1/2 transition-colors group-hover:text-primary"
                                        >
                                            <Clock
                                                class="h-3.5 w-3.5 text-muted-foreground/40"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="group mb-6 flex items-center justify-between rounded-xl border border-primary/10 bg-primary/5 p-4"
                                >
                                    <div>
                                        <p
                                            class="mb-0.5 text-[8px] font-black tracking-[0.2em] text-primary/60 uppercase"
                                        >
                                            Target Mission
                                        </p>
                                        <h4
                                            class="text-base font-black tracking-tight uppercase transition-colors group-hover:text-primary"
                                        >
                                            {{ selectedAssignment.title }}
                                        </h4>
                                    </div>
                                    <BookOpen
                                        class="h-6 w-6 opacity-10 transition-all duration-500 group-hover:scale-110 group-hover:opacity-20"
                                    />
                                </div>

                                <!-- Upload Zone -->
                                <div
                                    @click="fileInput?.click()"
                                    @mousemove="handleMouseMove"
                                    class="upload-zone group relative flex cursor-pointer flex-col items-center justify-center gap-4 overflow-hidden rounded-2xl border-2 border-dashed border-primary/20 p-8 transition-all duration-700 hover:border-primary hover:bg-primary/[0.03]"
                                    :class="{
                                        'opacity-40 grayscale-[0.5]':
                                            !selectedAssignmentId,
                                    }"
                                >
                                    <!-- Dynamic Radial Glow -->
                                    <div
                                        class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-700 group-hover:opacity-100"
                                        :style="{
                                            background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.15), transparent 40%)`,
                                        }"
                                    ></div>

                                    <input
                                        type="file"
                                        class="hidden"
                                        ref="fileInput"
                                        @change="
                                            (e: any) =>
                                                (form.file = e.target.files[0])
                                        "
                                    />

                                    <div
                                        v-if="!form.file"
                                        class="relative z-10 flex flex-col items-center gap-4"
                                    >
                                        <div class="relative">
                                            <div
                                                class="absolute inset-0 rounded-2xl bg-primary opacity-0 blur-2xl transition-all duration-700 group-hover:opacity-20"
                                            ></div>
                                            <div
                                                class="flex h-16 w-16 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 shadow-2xl transition-all duration-700 group-hover:scale-110 group-hover:border-primary/40"
                                            >
                                                <FileUp
                                                    class="h-6 w-6 text-primary"
                                                />
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <p
                                                class="text-[11px] font-black tracking-[0.2em] uppercase"
                                            >
                                                Initialize Transmission
                                            </p>
                                            <div
                                                class="mt-2 flex items-center gap-3"
                                            >
                                                <span
                                                    class="rounded-full border border-white/5 bg-white/5 px-2 py-0.5 text-[8px] font-bold tracking-widest text-muted-foreground/40 uppercase"
                                                    >PDF / PNG</span
                                                >
                                                <span
                                                    class="rounded-full border border-white/5 bg-white/5 px-2 py-0.5 text-[8px] font-bold tracking-widest text-muted-foreground/40 uppercase"
                                                    >MAX 10MB</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="relative z-10 flex flex-col items-center gap-4"
                                    >
                                        <div
                                            class="flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 shadow-[0_0_30px_rgba(16,185,129,0.2)]"
                                        >
                                            <FileText
                                                class="h-6 w-6 text-emerald-400"
                                            />
                                        </div>
                                        <div class="text-center">
                                            <p
                                                class="max-w-[250px] truncate rounded-lg border border-emerald-500/10 bg-emerald-500/5 px-3 py-1.5 text-[10px] font-black tracking-tight text-emerald-400 uppercase"
                                            >
                                                {{
                                                    form.file.name.toUpperCase()
                                                }}
                                            </p>
                                            <button
                                                @click.stop="form.file = null"
                                                class="mx-auto mt-3 flex items-center gap-2 text-[8px] font-black tracking-[0.25em] text-red-400/80 uppercase decoration-red-400/40 underline-offset-4 transition-all hover:scale-110 hover:text-red-400 hover:underline"
                                            >
                                                Discard Intelligence
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Corner accents -->
                                    <div
                                        class="absolute top-3 left-3 h-3 w-3 rounded-tl-md border-t-2 border-l-2 border-primary/20"
                                    ></div>
                                    <div
                                        class="absolute top-3 right-3 h-3 w-3 rounded-tr-md border-t-2 border-r-2 border-primary/20"
                                    ></div>
                                    <div
                                        class="absolute bottom-3 left-3 h-3 w-3 rounded-bl-md border-b-2 border-l-2 border-primary/20"
                                    ></div>
                                    <div
                                        class="absolute right-3 bottom-3 h-3 w-3 rounded-br-md border-r-2 border-b-2 border-primary/20"
                                    ></div>
                                </div>

                                <div class="relative z-10 mt-8 flex gap-4">
                                    <button
                                        @click="closeUploadModal"
                                        class="flex-1 rounded-xl border border-white/10 bg-white/5 px-6 py-4 text-[9px] font-black tracking-[0.3em] uppercase transition-all hover:border-white/20 hover:bg-white/10 active:scale-95"
                                    >
                                        Abort
                                    </button>
                                    <button
                                        :disabled="
                                            !form.file ||
                                            form.processing ||
                                            !selectedAssignmentId
                                        "
                                        @click="submitAssignment"
                                        :class="[
                                            'group/btn relative flex-[1.5] overflow-hidden rounded-xl px-6 py-4 text-[9px] font-black tracking-[0.3em] uppercase shadow-2xl transition-all active:scale-95',
                                            !form.file ||
                                            form.processing ||
                                            !selectedAssignmentId
                                                ? 'border border-border/10 bg-muted/10 text-muted-foreground/40 grayscale'
                                                : 'border border-primary/30 bg-primary text-primary-foreground hover:shadow-[0_0_40px_rgba(var(--primary-rgb),0.5)]',
                                        ]"
                                    >
                                        <div
                                            class="absolute inset-0 translate-x-[-100%] bg-white/20 transition-transform duration-1000 group-hover/btn:translate-x-[100%]"
                                        ></div>
                                        <span
                                            class="relative z-10 flex items-center justify-center gap-2"
                                        >
                                            {{
                                                form.processing
                                                    ? 'TRANSMITTING...'
                                                    : 'Confirm Transmission'
                                            }}
                                            <Sparkles
                                                v-if="
                                                    !form.processing &&
                                                    form.file &&
                                                    selectedAssignmentId
                                                "
                                                class="h-3.5 w-3.5"
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>

<style scoped>
.surface-card {
    background: rgba(255, 255, 255, 0.015);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 1.25rem;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-hover:hover {
    background: rgba(var(--primary-rgb), 0.03);
    border-color: rgba(var(--primary-rgb), 0.15);
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 20px 40px -20px rgba(0, 0, 0, 0.5);
}

.orb {
    will-change: transform;
}

.assignment-card {
    will-change: transform, opacity;
}

@keyframes scan-horizontal {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(1000%);
    }
}

.animate-scan-horizontal {
    animation: scan-horizontal 3s linear infinite;
}

select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='rgba(255,255,255,0.4)' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1rem;
}
</style>
