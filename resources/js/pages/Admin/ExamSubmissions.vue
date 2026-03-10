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
                <h1 class="text-3xl font-bold tracking-tight">Exam Submissions</h1>
                <p class="text-muted-foreground text-lg">View and manage student exam submissions.</p>
            </div>

            <!-- Exams Grid -->
            <div v-if="exams.length > 0" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="exam in exams"
                    :key="exam.id"
                    :href="`/admin/exams/${exam.id}/submissions`"
                    class="group relative overflow-hidden rounded-2xl border border-border/50 bg-card/50 backdrop-blur-md p-6 hover:border-primary/50 transition-all duration-300 hover:shadow-lg hover:shadow-primary/10"
                >
                    <div class="absolute -right-24 -top-24 w-48 h-48 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors duration-300"></div>

                    <div class="relative space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold group-hover:text-primary transition-colors">
                                    {{ exam.title }}
                                </h3>
                                <p class="text-muted-foreground text-sm line-clamp-2 mt-1">{{ exam.description }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <BookOpen class="w-5 h-5 text-primary/60" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-sm text-muted-foreground pt-2 border-t border-border/30">
                            <Users2 class="w-4 h-4" />
                            <span>{{ exam.submissions_count }} submission{{ exam.submissions_count !== 1 ? 's' : '' }}</span>
                            <ChevronRight class="w-4 h-4 ml-auto group-hover:translate-x-0.5 transition-transform" />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-center space-y-4">
                <div class="p-4 rounded-full bg-muted/30">
                    <BookOpen class="w-12 h-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No exams found</h3>
                    <p class="text-muted-foreground">There are no exams to review yet.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
