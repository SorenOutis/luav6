<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Users2, ChevronRight } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Exam {
    id: number;
    title: string;
    description: string;
    status: string;
    submissions_count: number;
}

const props = defineProps<{
    exams: Exam[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exam Submissions', href: '/admin/exams/submissions' },
];
</script>

<template>
    <Head title="Exam Submissions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10">
            <!-- Header Section -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">
                    Exam Submissions
                </h1>
                <p class="text-lg text-muted-foreground">
                    View and manage student exam submissions.
                </p>
            </div>

            <!-- Exams Grid -->
            <div
                v-if="exams.length > 0"
                class="grid gap-6 md:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="exam in exams"
                    :key="exam.id"
                    :href="`/admin/exams/${exam.id}/submissions`"
                    class="group relative overflow-hidden rounded-2xl border border-border/50 bg-card/50 p-6 backdrop-blur-md transition-all duration-300 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/10"
                >
                    <div
                        class="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-primary/5 blur-3xl transition-colors duration-300 group-hover:bg-primary/10"
                    ></div>

                    <div class="relative space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3
                                    class="text-xl font-bold transition-colors group-hover:text-primary"
                                >
                                    {{ exam.title }}
                                </h3>
                                <p
                                    class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ exam.description }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <BookOpen class="h-5 w-5 text-primary/60" />
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-2 border-t border-border/30 pt-2 text-sm text-muted-foreground"
                        >
                            <Users2 class="h-4 w-4" />
                            <span
                                >{{ exam.submissions_count }} submission{{
                                    exam.submissions_count !== 1 ? 's' : ''
                                }}</span
                            >
                            <ChevronRight
                                class="ml-auto h-4 w-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center space-y-4 py-20 text-center"
            >
                <div class="rounded-full bg-muted/30 p-4">
                    <BookOpen class="h-12 w-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No exams found</h3>
                    <p class="text-muted-foreground">
                        There are no exams to review yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
