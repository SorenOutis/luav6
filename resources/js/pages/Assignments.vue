<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
    Cpu
} from 'lucide-vue-next';

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
        list = list.filter(a => !a.submission?.submitted);
    } else {
        list = list.filter(a => a.submission?.submitted);
    }

    // Filter by month
    if (selectedMonth.value !== 'all') {
        const monthIndex = parseInt(selectedMonth.value);
        list = list.filter(a => {
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
            ease: "power2.in",
            onComplete: () => {
                showUploadModal.value = false;
                form.reset();
                selectedAssignment.value = null;
                selectedAssignmentId.value = '';
            }
        });
        gsap.to(overlay, {
            opacity: 0,
            duration: 0.4,
            ease: "power2.in"
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
        ease: "power3.out"
    });
    
    gsap.to(modal, {
        scale: 1,
        opacity: 1,
        y: 0,
        duration: 0.6,
        ease: "back.out(1.7)",
        delay: 0.1
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
        case 'Submitted': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'Graded': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'Pending': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        default: return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
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
    if (container.value) {
        const tl = gsap.timeline({
            defaults: { ease: "power4.out", duration: 1 }
        });

        tl.fromTo('.header-content', { opacity: 0, y: -20 }, { opacity: 1, y: 0 });
        tl.fromTo('.stats-card', { opacity: 0, scale: 0.9 }, { opacity: 1, scale: 1, stagger: 0.1 }, "-=0.5");
        tl.fromTo('.tabs-nav', { opacity: 0, y: 10 }, { opacity: 1, y: 0 }, "-=0.5");

        const orbs = container.value.querySelectorAll('.orb');
        orbs.forEach((orb, i) => {
            gsap.to(orb, {
                x: `random(-40, 40)`,
                y: `random(-40, 40)`,
                duration: 10 + i * 5,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut"
            });
        });
    }
});
</script>

<script lang="ts">
// Helper for Ziggy route if not globally available
declare const route: any;
</script>

<template>
    <Head title="Assignments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="container" class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Decorative Orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <div class="header-content flex flex-col md:flex-row md:items-center justify-between gap-6 z-10">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent uppercase">
                        Mission Briefings
                    </h1>
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60 mt-1">Complete your objectives to earn XP and advance your rank.</p>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Global Submit Button -->
                    <Button 
                        @click="selectedAssignment = null; selectedAssignmentId = ''; showUploadModal = true"
                        variant="default" 
                        size="sm" 
                        class="h-10 px-6 rounded-xl gap-2 text-[10px] font-extrabold uppercase tracking-[0.15em] shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] hover:shadow-[0_0_30px_rgba(var(--primary-rgb),0.5)] transition-all duration-500 bg-primary text-primary-foreground border border-primary/20"
                    >
                        <FileUp class="w-4 h-4" />
                        Submit Intel
                    </Button>

                    <!-- Month Filter -->
                    <div class="flex items-center gap-3 px-4 py-2 rounded-2xl surface-card border-border/10 h-10">
                        <Calendar class="w-3.5 h-3.5 text-muted-foreground/60" />
                        <select 
                            v-model="selectedMonth"
                            class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest focus:ring-0 cursor-pointer appearance-none pr-6 h-full"
                        >
                            <option v-for="month in months" :key="month.value" :value="month.value" class="bg-background text-foreground">
                                {{ month.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/5 border border-primary/10 h-10">
                        <TrendingUp class="w-3.5 h-3.5 text-primary" />
                        <span class="text-[10px] font-bold uppercase tracking-wider">Rank: Vanguard</span>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 z-10">
                <div class="stats-card surface-card p-6 relative overflow-hidden group premium-hover" @mousemove="handleMouseMove">
                    <!-- Hover Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.1), transparent 40%)` }">
                    </div>
                    <div class="absolute -right-6 -top-6 opacity-[0.03] group-hover:opacity-[0.06] transition-opacity duration-700 pointer-events-none">
                        <Clock class="w-32 h-32 rotate-12" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60">Active Objectives</p>
                        <h3 class="text-4xl font-black tracking-tighter mt-2">
                            {{ assignments.filter(a => !a.submission?.submitted).length }}
                        </h3>
                        <div class="mt-4 pt-4 border-t border-border/10">
                            <span class="text-[10px] font-bold text-muted-foreground/50 tracking-wide uppercase">Immediate priority</span>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card surface-card p-6 relative overflow-hidden group premium-hover" @mousemove="handleMouseMove">
                    <!-- Hover Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(255, 255, 255, 0.05), transparent 40%)` }">
                    </div>
                    <div class="absolute -right-6 -top-6 opacity-[0.03] group-hover:opacity-[0.06] transition-opacity duration-700 pointer-events-none">
                        <CheckCircle2 class="w-32 h-32 rotate-12" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60">Completed Missions</p>
                        <h3 class="text-4xl font-black tracking-tighter mt-2">
                            {{ assignments.filter(a => a.submission?.submitted).length }}
                        </h3>
                        <div class="mt-4 pt-4 border-t border-border/10">
                            <span class="text-[10px] font-bold text-muted-foreground/50 tracking-wide uppercase">Objectives achieved</span>
                        </div>
                    </div>
                </div>

                <div class="stats-card surface-card p-6 relative overflow-hidden group premium-hover" @mousemove="handleMouseMove">
                    <!-- Hover Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.1), transparent 40%)` }">
                    </div>
                    <div class="absolute -right-6 -top-6 opacity-[0.03] group-hover:opacity-[0.06] transition-opacity duration-700 pointer-events-none">
                        <Sparkles class="w-32 h-32 rotate-12" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60">Performance Rank</p>
                        <h3 class="text-4xl font-black tracking-tighter mt-2">A+</h3>
                        <div class="mt-4 pt-4 border-t border-border/10">
                            <span class="text-[10px] font-bold text-muted-foreground/50 tracking-wide uppercase">Top 1% of battalion</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs-nav z-10 flex border-b border-border/10">
                <button 
                    @click="activeTab = 'pending'"
                    :class="[
                        'px-8 py-4 text-[10px] font-black uppercase tracking-[0.2em] transition-all relative overflow-hidden',
                        activeTab === 'pending' ? 'text-primary' : 'text-muted-foreground/40 hover:text-muted-foreground'
                    ]"
                >
                    Pending
                    <div v-if="activeTab === 'pending'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary animate-in slide-in-from-left duration-300"></div>
                </button>
                <button 
                    @click="activeTab = 'completed'"
                    :class="[
                        'px-8 py-4 text-[10px] font-black uppercase tracking-[0.2em] transition-all relative overflow-hidden',
                        activeTab === 'completed' ? 'text-primary' : 'text-muted-foreground/40 hover:text-muted-foreground'
                    ]"
                >
                    Completed
                    <div v-if="activeTab === 'completed'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary animate-in slide-in-from-left duration-300"></div>
                </button>
            </div>

            <!-- Assignments Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 z-10">
                <TransitionGroup 
                    enter-active-class="animate-in fade-in slide-in-from-bottom-4 duration-500"
                    leave-active-class="animate-out fade-out slide-out-to-top-4 duration-300 absolute"
                >
                    <div v-for="assignment in filteredAssignments" :key="assignment.id" 
                        class="assignment-card surface-card p-8 group premium-hover relative overflow-hidden"
                        @mousemove="handleMouseMove"
                    >
                        <!-- Hover Bloom Effect -->
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                            :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.15), transparent 40%)` }">
                        </div>

                        <!-- Silhouette Background Icon -->
                        <div class="absolute -right-12 -bottom-12 opacity-[0.03] group-hover:opacity-[0.05] transition-all duration-700 pointer-events-none rotate-12 group-hover:rotate-0 scale-110">
                            <BookOpen class="w-64 h-64" />
                        </div>

                        <div class="relative z-10 h-full flex flex-col">
                            <div class="flex items-start justify-between mb-6">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
                                            {{ assignment.course?.name || 'General' }}
                                        </div>
                                        <div :class="[
                                            'px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border', 
                                            getStatusColor(assignment.submission?.status || 'Pending').split(' ').filter(c => !c.includes('bg-')).join(' '),
                                            getStatusColor(assignment.submission?.status || 'Pending').split(' ').find(c => c.includes('bg-'))?.replace('/10', '/20')
                                        ]">
                                            {{ assignment.submission?.status || 'Pending' }}
                                        </div>
                                    </div>
                                    <h3 class="text-2xl font-black tracking-tighter leading-tight group-hover:text-primary transition-colors duration-500">
                                        {{ assignment.title }}
                                    </h3>
                                </div>
                                
                                <div class="text-right">
                                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground/40 mb-1">
                                        {{ assignment.submission?.submitted ? 'Transmission' : 'Deadline' }}
                                    </p>
                                    <p class="text-xs font-bold font-mono tracking-tight" :class="isOverdue(assignment.due_date) && !assignment.submission?.submitted ? 'text-red-500' : 'text-muted-foreground'">
                                        {{ assignment.submission?.submitted_at ? new Date(assignment.submission.submitted_at).toLocaleDateString() : (assignment.due_date || 'UNDEFINED') }}
                                    </p>
                                </div>
                            </div>

                            <p class="text-muted-foreground/70 text-sm leading-relaxed line-clamp-2 mb-8 flex-grow font-medium">
                                {{ assignment.description || 'No specialized mission intelligence provided for this objective. Proceed with standard operational procedures.' }}
                            </p>

                            <div class="flex items-center justify-between mt-auto pt-8 border-t border-border/10">
                                <!-- Enhanced Mission Status -->
                                <div v-if="assignment.submission?.submitted" class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/5 border border-emerald-500/10 flex items-center justify-center">
                                        <FileText class="w-5 h-5 text-emerald-500" />
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-muted-foreground/40">Secure Submission</p>
                                        <p class="text-xs font-bold font-mono text-emerald-500/80">VERIFIED</p>
                                    </div>
                                </div>
                                
                                <div v-else class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] text-amber-500/80">Objective Incomplete</span>
                                </div>

                                <div class="flex gap-3">
                                    <Button v-if="assignment.submission?.submitted" variant="outline" size="sm" class="h-9 px-4 rounded-xl gap-2 bg-transparent border-white/5 hover:bg-white/5 text-[10px] font-bold uppercase tracking-wider">
                                        <Download class="w-3.5 h-3.5" />
                                        Intel
                                    </Button>
                                    <Button 
                                        v-if="!assignment.submission?.submitted" 
                                        @click="selectedAssignment = assignment; selectedAssignmentId = assignment.id; showUploadModal = true"
                                        variant="default" 
                                        size="sm" 
                                        class="h-9 px-6 rounded-xl gap-2 text-[10px] font-extrabold uppercase tracking-[0.15em] shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] hover:shadow-[0_0_30px_rgba(var(--primary-rgb),0.5)] transition-all duration-500"
                                    >
                                        <FileUp class="w-3.5 h-3.5" />
                                        Submit Intel
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>

            <!-- Empty State -->
            <div v-if="filteredAssignments.length === 0" class="flex flex-col items-center justify-center py-20 z-10">
                <div class="w-20 h-20 rounded-3xl bg-muted/5 border border-border/10 flex items-center justify-center mb-6">
                    <BookOpen class="w-8 h-8 text-muted-foreground/20" />
                </div>
                <h3 class="text-xl font-black tracking-tighter uppercase text-muted-foreground/40">No Missions Found</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/20 mt-2">Adjust your filters or standby for new objectives.</p>
            </div>

            <!-- Upload Modal -->
            <Transition @enter="onModalEnter" :css="false">
                <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="modal-overlay absolute inset-0 bg-background/80 backdrop-blur-2xl" @click="closeUploadModal"></div>
                    
                    <div class="modal-content w-full max-w-lg surface-card p-0.5 relative z-10 rounded-[2rem] shadow-[0_0_50px_-12px_rgba(var(--primary-rgb),0.3)] border border-primary/20 bg-gradient-to-b from-primary/10 to-transparent overflow-hidden">
                        <!-- Modal Inner Content -->
                        <div class="bg-background/40 backdrop-blur-md rounded-[1.9rem] p-6 relative overflow-hidden">
                            <!-- Tactical Background UI -->
                            <div class="absolute inset-0 pointer-events-none opacity-20">
                                <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                                <div class="absolute left-10 top-0 h-full w-px bg-gradient-to-b from-transparent via-primary/20 to-transparent"></div>
                                <div class="absolute right-10 top-0 h-full w-px bg-gradient-to-b from-transparent via-primary/20 to-transparent"></div>
                            </div>

                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center shadow-[0_0_20px_rgba(var(--primary-rgb),0.2)]">
                                            <Cpu class="w-5 h-5 text-primary animate-pulse" />
                                        </div>
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-[0.3em] text-primary/80">Transmission Protocol 7-A</p>
                                            <h2 class="text-2xl font-black tracking-tighter uppercase leading-none mt-1">Submit Intel</h2>
                                        </div>
                                    </div>
                                    <div class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10">
                                        <div class="flex items-center gap-2">
                                            <ShieldCheck class="w-3.5 h-3.5 text-emerald-400" />
                                            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-400">Secure</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Assignment Selector -->
                                <div v-if="!selectedAssignment" class="mb-6 space-y-2">
                                    <label class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">Designate Objective</label>
                                    <div class="relative group">
                                        <select 
                                            v-model="selectedAssignmentId"
                                            class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-5 py-3 text-[10px] font-bold uppercase tracking-[0.15em] appearance-none focus:ring-2 focus:ring-primary/40 focus:border-primary/40 outline-none transition-all cursor-pointer hover:bg-white/[0.05]"
                                        >
                                            <option value="" disabled>Awaiting Objective Designation...</option>
                                            <option v-for="a in assignments.filter(x => !x.submission?.submitted)" :key="a.id" :value="a.id" class="bg-[#0a0a0a] text-foreground">
                                                {{ a.title }}
                                            </option>
                                        </select>
                                        <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none group-hover:text-primary transition-colors">
                                            <Clock class="w-3.5 h-3.5 text-muted-foreground/40" />
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mb-6 p-4 rounded-xl bg-primary/5 border border-primary/10 flex items-center justify-between group">
                                    <div>
                                        <p class="text-[8px] font-black uppercase tracking-[0.2em] text-primary/60 mb-0.5">Target Mission</p>
                                        <h4 class="text-base font-black tracking-tight uppercase group-hover:text-primary transition-colors">{{ selectedAssignment.title }}</h4>
                                    </div>
                                    <BookOpen class="w-6 h-6 opacity-10 group-hover:scale-110 group-hover:opacity-20 transition-all duration-500" />
                                </div>

                                <!-- Upload Zone -->
                                <div 
                                    @click="fileInput?.click()"
                                    @mousemove="handleMouseMove"
                                    class="upload-zone border-2 border-dashed border-primary/20 rounded-2xl p-8 flex flex-col items-center justify-center gap-4 hover:border-primary hover:bg-primary/[0.03] transition-all duration-700 cursor-pointer group relative overflow-hidden"
                                    :class="{'opacity-40 grayscale-[0.5]': !selectedAssignmentId}"
                                >
                                    <!-- Dynamic Radial Glow -->
                                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                                        :style="{ background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.15), transparent 40%)` }">
                                    </div>

                                    <input 
                                        type="file" 
                                        class="hidden" 
                                        ref="fileInput"
                                        @change="(e: any) => form.file = e.target.files[0]"
                                    >
                                    
                                    <div v-if="!form.file" class="flex flex-col items-center gap-4 relative z-10">
                                        <div class="relative">
                                            <div class="absolute inset-0 bg-primary rounded-2xl blur-2xl opacity-0 group-hover:opacity-20 transition-all duration-700"></div>
                                            <div class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center group-hover:scale-110 group-hover:border-primary/40 transition-all duration-700 shadow-2xl">
                                                <FileUp class="w-6 h-6 text-primary" />
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-[11px] font-black uppercase tracking-[0.2em]">Initialize Transmission</p>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="text-[8px] font-bold text-muted-foreground/40 uppercase tracking-widest px-2 py-0.5 bg-white/5 rounded-full border border-white/5">PDF / PNG</span>
                                                <span class="text-[8px] font-bold text-muted-foreground/40 uppercase tracking-widest px-2 py-0.5 bg-white/5 rounded-full border border-white/5">MAX 10MB</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div v-else class="flex flex-col items-center gap-4 relative z-10">
                                        <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shadow-[0_0_30px_rgba(16,185,129,0.2)]">
                                            <FileText class="w-6 h-6 text-emerald-400" />
                                        </div>
                                        <div class="text-center">
                                            <p class="text-[10px] font-black text-emerald-400 truncate max-w-[250px] tracking-tight uppercase px-3 py-1.5 bg-emerald-500/5 rounded-lg border border-emerald-500/10">{{ form.file.name.toUpperCase() }}</p>
                                            <button @click.stop="form.file = null" class="text-[8px] font-black uppercase tracking-[0.25em] text-red-400/80 mt-3 hover:text-red-400 hover:scale-110 transition-all flex items-center gap-2 mx-auto decoration-red-400/40 underline-offset-4 hover:underline">
                                                Discard Intelligence
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Corner accents -->
                                    <div class="absolute top-3 left-3 w-3 h-3 border-t-2 border-l-2 border-primary/20 rounded-tl-md"></div>
                                    <div class="absolute top-3 right-3 w-3 h-3 border-t-2 border-r-2 border-primary/20 rounded-tr-md"></div>
                                    <div class="absolute bottom-3 left-3 w-3 h-3 border-b-2 border-l-2 border-primary/20 rounded-bl-md"></div>
                                    <div class="absolute bottom-3 right-3 w-3 h-3 border-b-2 border-r-2 border-primary/20 rounded-br-md"></div>
                                </div>

                                <div class="flex gap-4 mt-8 relative z-10">
                                    <button 
                                        @click="closeUploadModal"
                                        class="flex-1 px-6 py-4 rounded-xl bg-white/5 border border-white/10 text-[9px] font-black uppercase tracking-[0.3em] hover:bg-white/10 hover:border-white/20 transition-all active:scale-95"
                                    >
                                        Abort
                                    </button>
                                    <button 
                                        :disabled="!form.file || form.processing || !selectedAssignmentId"
                                        @click="submitAssignment"
                                        :class="[
                                            'flex-[1.5] px-6 py-4 rounded-xl text-[9px] font-black uppercase tracking-[0.3em] transition-all active:scale-95 shadow-2xl relative overflow-hidden group/btn',
                                            !form.file || form.processing || !selectedAssignmentId 
                                                ? 'bg-muted/10 border border-border/10 text-muted-foreground/40 grayscale' 
                                                : 'bg-primary text-primary-foreground border border-primary/30 hover:shadow-[0_0_40px_rgba(var(--primary-rgb),0.5)]'
                                        ]"
                                    >
                                        <div class="absolute inset-0 bg-white/20 translate-x-[-100%] group-hover/btn:translate-x-[100%] transition-transform duration-1000"></div>
                                        <span class="relative z-10 flex items-center justify-center gap-2">
                                            {{ form.processing ? 'TRANSMITTING...' : 'Confirm Transmission' }}
                                            <Sparkles v-if="!form.processing && form.file && selectedAssignmentId" class="w-3.5 h-3.5" />
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

select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='rgba(255,255,255,0.4)' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1rem;
}
</style>
