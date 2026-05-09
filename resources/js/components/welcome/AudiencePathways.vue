<script setup lang="ts">
import { BarChart3, GraduationCap, ShieldCheck, Sparkles, Trophy, Users } from 'lucide-vue-next';

const pathways = [
    {
        title: 'Students',
        eyebrow: 'Engagement Layer',
        icon: Trophy,
        points: ['Learning maps and quests', 'Badges, streaks, and games', 'Clear feedback after every activity'],
    },
    {
        title: 'Teachers',
        eyebrow: 'Classroom Layer',
        icon: GraduationCap,
        points: ['Assignments and timed assessments', 'AI-assisted essay feedback', 'Grades and section progress in one place'],
    },
    {
        title: 'School Admins',
        eyebrow: 'Operations Layer',
        icon: ShieldCheck,
        points: ['Sections, users, and school levels', 'Analytics for performance monitoring', 'Backup, restore, and admin controls'],
    },
];

const highlights = [
    { label: 'Class Setup', value: 'Sections', icon: Users },
    { label: 'Checking Flow', value: 'AI Assist', icon: Sparkles },
    { label: 'Decision View', value: 'Analytics', icon: BarChart3 },
];
</script>

<template>
    <section class="reveal-section mt-24 lg:mt-40 border-y border-border/20 py-12 lg:py-16">
        <div class="mb-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-5 flex items-center gap-4">
                    <div class="h-px w-12 bg-primary"></div>
                    <p class="text-[10px] font-black uppercase tracking-[0.4em] text-primary" data-scramble>Built For Every School Role</p>
                </div>
                <h2 class="max-w-3xl text-3xl font-black uppercase tracking-tight sm:text-4xl lg:text-6xl">
                    One system, three clear experiences.
                </h2>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <article
                v-for="pathway in pathways"
                :key="pathway.title"
                class="group border border-border/30 bg-card/50 p-6 transition-colors hover:border-primary/40 hover:bg-muted/30 lg:p-8"
            >
                <div class="mb-8 flex items-start justify-between gap-5">
                    <div>
                        <p class="mb-2 text-[9px] font-black uppercase tracking-[0.3em] text-primary">{{ pathway.eyebrow }}</p>
                        <h3 class="text-2xl font-black uppercase tracking-tight">{{ pathway.title }}</h3>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center border border-primary/20 bg-primary/10">
                        <component :is="pathway.icon" class="h-5 w-5 text-primary" />
                    </div>
                </div>

                <ul class="space-y-4">
                    <li v-for="point in pathway.points" :key="point" class="flex items-start gap-3 text-sm font-medium text-muted-foreground">
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                        <span>{{ point }}</span>
                    </li>
                </ul>
            </article>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div v-for="item in highlights" :key="item.label" class="flex items-center gap-4 border border-border/20 bg-background/50 p-4">
                <component :is="item.icon" class="h-5 w-5 text-primary" />
                <div>
                    <p class="text-[8px] font-black uppercase tracking-[0.25em] text-muted-foreground">{{ item.label }}</p>
                    <p class="text-sm font-black uppercase tracking-widest">{{ item.value }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
