<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, User, Calendar, FileText, Zap } from 'lucide-vue-next';
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
    // Which set of the exam this answer belongs to (null before sets ship).
    set_title?: string | null;
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
    {
        title: props.exam.title,
        href: `/admin/exams/${props.exam.id}/submissions`,
    },
];

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
                    class="group inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                >
                    <ChevronLeft
                        class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"
                    />
                    Back to Exams
                </Link>
            </div>

            <!-- Header Section -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">
                    {{ exam.title }}
                </h1>
                <p class="text-lg text-muted-foreground">
                    {{ exam.description }}
                </p>
            </div>

            <!-- Submissions List -->
            <div v-if="submissions.length > 0" class="space-y-6">
                <div
                    v-for="submission in submissions"
                    :key="submission.id"
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl"
                >
                    <!-- Submission Header -->
                    <div
                        class="border-b border-border/30 bg-gradient-to-r from-primary/5 to-transparent p-6"
                    >
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <div
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    Student
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <User class="h-4 w-4 text-primary" />
                                    <span class="font-semibold">{{
                                        submission.user_name
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    Part
                                </div>
                                <div class="mt-1 font-semibold">
                                    {{ submission.part_title }}
                                    <span
                                        v-if="submission.set_title"
                                        class="ml-1 text-xs font-medium text-muted-foreground"
                                        >({{ submission.set_title }})</span
                                    >
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    Submitted
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-primary" />
                                    <span class="text-sm">{{
                                        submission.submitted_at
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    Status
                                </div>
                                <div class="mt-1">
                                    <span
                                        class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                                    >
                                        {{ submission.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Answers -->
                    <div class="space-y-6 p-6">
                        <div
                            v-for="answer in submission.answers"
                            :key="answer.question_number"
                            class="space-y-3 rounded-xl border border-border/40 bg-muted/20 p-5"
                        >
                            <!-- Question Header -->
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-xs font-bold text-primary"
                                >
                                    {{ answer.question_number }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex items-center gap-2">
                                        <span
                                            class="rounded bg-muted px-2 py-0.5 text-[10px] font-medium tracking-widest text-muted-foreground/70 uppercase"
                                        >
                                            {{
                                                formatType(answer.question_type)
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm leading-relaxed font-semibold whitespace-pre-wrap text-foreground"
                                    >
                                        {{ answer.question_text }}
                                    </p>
                                </div>
                            </div>

                            <!-- Answer Display -->
                            <div class="ml-11">
                                <div
                                    class="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    Answer:
                                </div>
                                <div
                                    class="rounded-lg border border-border/30 bg-background/50 p-3 text-sm text-foreground"
                                >
                                    <span
                                        v-if="
                                            answer.answer !== null &&
                                            answer.answer !== ''
                                        "
                                    >
                                        {{ answer.answer }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-muted-foreground italic"
                                        >No answer provided</span
                                    >
                                </div>

                                <!-- AI Assessment Display -->
                                <div
                                    v-if="
                                        answer.question_type === 'essay' &&
                                        (answer.ai_score !== undefined ||
                                            answer.ai_feedback !== undefined)
                                    "
                                    class="group/ai relative mt-4 space-y-3 overflow-hidden rounded-xl border border-primary/20 bg-primary/5 p-4"
                                >
                                    <div
                                        class="absolute top-0 right-0 h-12 w-12 translate-x-6 -translate-y-6 -rotate-45 bg-primary/5 transition-colors group-hover/ai:bg-primary/10"
                                    ></div>

                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-primary/20 bg-primary/10"
                                            >
                                                <Zap
                                                    class="h-3.5 w-3.5 animate-pulse text-primary"
                                                />
                                            </div>
                                            <span
                                                class="text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                                                >AI Assessment Analysis</span
                                            >
                                        </div>
                                        <div
                                            class="rounded-lg bg-primary px-2.5 py-1 text-[10px] font-black tracking-widest text-primary-foreground uppercase"
                                        >
                                            Score: {{ answer.ai_score }} /
                                            {{ answer.points ?? 1 }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center space-y-4 py-20 text-center"
            >
                <div class="rounded-full bg-muted/30 p-4">
                    <FileText class="h-12 w-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No submissions yet</h3>
                    <p class="text-muted-foreground">
                        Students haven't submitted any answers for this exam
                        yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
