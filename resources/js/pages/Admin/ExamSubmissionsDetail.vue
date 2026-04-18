<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, User, Calendar, FileText, CheckCircle2, HelpCircle, Zap } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Answer {
    question_number: number;
    question_text: string;
    question_type: string;
    points?: number;
    answer: string | number | null;
    ai_score?: number;
    ai_feedback?: string;
}

interface Submission {
    id: number;
    user_name: string;
    user_id: number;
    part_title: string;
    part_id: number;
    answers: Answer[];
    status: string;
    submitted_at: string;
    created_at: string;
}

interface Exam {
    id: number;
    title: string;
    description: string;
}

const props = defineProps<{
    exam: Exam;
    submissions: Submission[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exam Submissions', href: '/admin/exams/submissions' },
    { title: props.exam.title, href: `/admin/exams/${props.exam.id}/submissions` },
];

const getQuestionIcon = (type: string) => {
    const icons: Record<string, any> = {
        multiple_choice: HelpCircle,
        identification: FileText,
        essay: FileText,
        true_false: CheckCircle2,
    };
    return icons[type] || FileText;
};

const formatType = (type: string) => type.replace(/_/g, ' ').toUpperCase();
</script>

<template>
    <Head :title="`${exam.title} — Submissions`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10">
            <!-- Back button -->
            <div>
                <Link
                    href="/admin/exams/submissions"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group px-3 py-1.5 rounded-lg hover:bg-muted/50"
                >
                    <ChevronLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                    Back to Exams
                </Link>
            </div>

            <!-- Header Section -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">{{ exam.title }}</h1>
                <p class="text-muted-foreground text-lg">{{ exam.description }}</p>
            </div>

            <!-- Submissions List -->
            <div v-if="submissions.length > 0" class="space-y-6">
                <div v-for="submission in submissions" :key="submission.id"
                    class="rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl overflow-hidden">

                    <!-- Submission Header -->
                    <div class="bg-gradient-to-r from-primary/5 to-transparent p-6 border-b border-border/30">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <div class="text-xs text-muted-foreground uppercase tracking-widest font-semibold">Student</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <User class="w-4 h-4 text-primary" />
                                    <span class="font-semibold">{{ submission.user_name }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground uppercase tracking-widest font-semibold">Part</div>
                                <div class="mt-1 font-semibold">{{ submission.part_title }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground uppercase tracking-widest font-semibold">Submitted</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <Calendar class="w-4 h-4 text-primary" />
                                    <span class="text-sm">{{ submission.submitted_at }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground uppercase tracking-widest font-semibold">Status</div>
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                        {{ submission.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Answers -->
                    <div class="p-6 space-y-6">
                        <div v-for="answer in submission.answers" :key="answer.question_number"
                            class="rounded-xl border border-border/40 bg-muted/20 p-5 space-y-3">

                            <!-- Question Header -->
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0 text-xs font-bold text-primary">
                                    {{ answer.question_number }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-muted text-muted-foreground/70 font-medium uppercase tracking-widest">
                                            {{ formatType(answer.question_type) }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold leading-relaxed text-foreground whitespace-pre-wrap">{{ answer.question_text }}</p>
                                </div>
                            </div>

                            <!-- Answer Display -->
                            <div class="ml-11">
                                <div class="text-xs text-muted-foreground uppercase tracking-widest font-semibold mb-2">Answer:</div>
                                <div class="text-sm text-foreground bg-background/50 rounded-lg p-3 border border-border/30">
                                    <span v-if="answer.answer !== null && answer.answer !== ''">
                                        {{ answer.answer }}
                                    </span>
                                    <span v-else class="text-muted-foreground italic">No answer provided</span>
                                </div>

                                <!-- AI Assessment Display -->
                                <div v-if="answer.question_type === 'essay' && (answer.ai_score !== undefined || answer.ai_feedback !== undefined)" 
                                    class="mt-4 p-4 rounded-xl bg-primary/5 border border-primary/20 space-y-3 relative overflow-hidden group/ai">
                                    <div class="absolute top-0 right-0 w-12 h-12 bg-primary/5 -rotate-45 translate-x-6 -translate-y-6 group-hover/ai:bg-primary/10 transition-colors"></div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center">
                                                <Zap class="w-3.5 h-3.5 text-primary animate-pulse" />
                                            </div>
                                            <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em]">AI Assessment Analysis</span>
                                        </div>
                                        <div class="px-2.5 py-1 bg-primary text-primary-foreground font-black text-[10px] uppercase tracking-widest rounded-lg">
                                            Score: {{ answer.ai_score }} / {{ answer.points ?? 1 }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-center space-y-4">
                <div class="p-4 rounded-full bg-muted/30">
                    <FileText class="w-12 h-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No submissions yet</h3>
                    <p class="text-muted-foreground">Students haven't submitted any answers for this exam yet.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
