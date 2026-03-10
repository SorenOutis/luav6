<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { show as examsShow } from '@/routes/exams';
import { onMounted, ref, computed } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Calendar, Clock, ExternalLink, AlertCircle } from 'lucide-vue-next';

interface ExamPart {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    questions: { text: string; type: string }[] | null;
}

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    duration_minutes: number;
    status: string;
    url: string | null;
    parts: ExamPart[];
}

const props = defineProps<{
    exams: Exam[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
];

const examContainer = ref<HTMLElement | null>(null);

const formatDateTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    if (!examContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power4.out', duration: 1.1 }
    });

    // Initial states
    gsap.set('.exam-hero', { opacity: 0, y: 40, scale: 0.98 });
    gsap.set('.exam-card', {
        opacity: 0,
        y: 60,
        scale: 0.96,
        rotationX: -8,
        transformOrigin: 'center top'
    });

    // Hero entrance
    tl.to('.exam-hero', { opacity: 1, y: 0, scale: 1 });

    // Card entrance with depth and stagger
    tl.to(
        '.exam-card',
        {
            opacity: 1,
            y: 0,
            scale: 1,
            rotationX: 0,
            stagger: 0.15,
            clearProps: 'transform,opacity'
        },
        '-=0.4'
    );

    // Background orb animation
    const orbs = examContainer.value.querySelectorAll('.orb');
    orbs.forEach((orb, i) => {
        gsap.to(orb, {
            x: `random(-60, 60)`,
            y: `random(-60, 60)`,
            duration: 15 + i * 5,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });
    });
});
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="examContainer" class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Glassy background decorative orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Header Section -->
            <div class="animate-section exam-hero space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">Upcoming Exams</h1>
                <p class="text-muted-foreground text-lg">Manage your assessments and upcoming academic challenges.</p>
            </div>

            <!-- Exam Grid -->
            <div v-if="exams.length > 0" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <div 
                    v-for="exam in exams" 
                    :key="exam.id"
                    class="animate-section exam-card surface-card group p-6 flex flex-col justify-between hover:border-primary/50 transition-all duration-500 overflow-hidden relative"
                >
                    <!-- Glossy accent -->
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors duration-500"></div>
                    
                    <div class="space-y-4 relative z-10">
                        <div class="flex justify-between items-start">
                            <div class="px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-xs font-semibold text-primary uppercase tracking-wider">
                                {{ exam.status }}
                            </div>
                            <div class="flex items-center text-muted-foreground text-xs gap-1">
                                <Clock class="w-3.5 h-3.5" />
                                {{ exam.duration_minutes }} mins
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold group-hover:text-primary transition-colors duration-300">{{ exam.title }}</h3>
                            <p class="text-muted-foreground text-sm line-clamp-2 mt-1">{{ exam.description }}</p>
                        </div>

                        <div class="space-y-3 pt-2">
                            <div class="flex items-center gap-3 text-sm text-foreground/80">
                                <Calendar class="w-4 h-4 text-primary" />
                                {{ formatDateTime(exam.exam_date) }}
                            </div>

                            <!-- Exam Parts Summary -->
                            <div v-if="exam.parts.length > 0" class="pt-4 space-y-2 border-t border-border/30">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/60">Exam Structure</p>
                                <div class="flex flex-wrap gap-2">
                                    <div 
                                        v-for="part in exam.parts" 
                                        :key="part.id"
                                        class="px-2 py-1 rounded bg-muted/50 border border-border/30 text-[10px] flex items-center gap-1.5"
                                    >
                                        <div class="w-1 h-1 rounded-full bg-primary/40"></div>
                                        <span class="font-medium">{{ part.title }}</span>
                                        <span class="text-muted-foreground/70 opacity-60">({{ (part.questions?.length ?? 0) }} q)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 relative z-10">
                        <a 
                            v-if="exam.url" 
                            :href="exam.url" 
                            target="_blank"
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:opacity-90 transition-all shadow-lg shadow-primary/20"
                        >
                            Start Exam
                            <ExternalLink class="w-4 h-4" />
                        </a>
                        <Link 
                            v-else
                            :href="examsShow(exam.id).url"
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:opacity-90 transition-all shadow-lg shadow-primary/20"
                        >
                            Start Exam
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="animate-section flex flex-col items-center justify-center py-20 text-center space-y-4 surface-card border-dashed">
                <div class="p-4 rounded-full bg-muted/30">
                    <AlertCircle class="w-12 h-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No active exams found</h3>
                    <p class="text-muted-foreground">Keep an eye out! Your instructor will post new exams here.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../css/app.css";

.perspective-\[1000px\] {
    perspective: 1000px;
}

.surface-card {
    @apply bg-card/50 backdrop-blur-md border border-border/50 rounded-2xl;
}

.animate-section {
    will-change: transform, opacity;
}
</style>
