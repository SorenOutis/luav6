<script setup lang="ts">
import { ArrowUpRight } from 'lucide-vue-next';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

const REPOSITORY_URL = 'https://github.com/SorenOutis/luav6';

const updates = [
    {
        date: '2026-10-28',
        label: 'Oct 28, 2026',
        tag: 'Community',
        title: 'Community Lounge is now an installable app',
        summary:
            'Add the lounge to your home screen with an offline shell and a smart install prompt.',
    },
    {
        date: '2026-10-23',
        label: 'Oct 23, 2026',
        tag: 'Learners',
        title: 'Student Community Lounge',
        summary:
            'A section-scoped space for posts, stories, and presence — moderated by the school.',
    },
    {
        date: '2026-10-18',
        label: 'Oct 18, 2026',
        tag: 'Echo',
        title: 'Echo agent hardening',
        summary:
            'Intent-scoped tools and workspace-safe writes: Echo can never act outside your school.',
    },
    {
        date: '2026-10-17',
        label: 'Oct 17, 2026',
        tag: 'Teachers',
        title: 'Grading terms for assignments',
        summary:
            'Assign work to a grading term so activity records and scores roll up correctly.',
    },
];
</script>

<template>
    <section
        id="updates"
        class="welcome-updates scroll-mt-32 py-20 sm:py-28"
        aria-labelledby="updates-heading"
    >
        <div
            class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_2fr] lg:gap-16 xl:max-w-7xl"
        >
            <RevealGroup>
                <p
                    class="rv flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-muted-foreground uppercase"
                    :style="{ '--rv-i': 0 }"
                >
                    <span class="text-brand">08</span>
                    <span class="h-px w-8 bg-border" aria-hidden="true"></span>
                    Latest updates
                </p>
                <h2
                    id="updates-heading"
                    class="rv mt-4 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl"
                    :style="{ '--rv-i': 1 }"
                >
                    Shipping every week.
                </h2>
                <a
                    :href="REPOSITORY_URL"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rv group mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-foreground underline-offset-4 hover:underline"
                    :style="{ '--rv-i': 2 }"
                >
                    Follow along on GitHub
                    <ArrowUpRight
                        class="h-4 w-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </a>
            </RevealGroup>

            <RevealGroup
                as="ol"
                :stagger="80"
                class="divide-y divide-border/70 border-y border-border/70"
            >
                <li
                    v-for="(update, index) in updates"
                    :key="update.date + update.title"
                    class="rv grid gap-2 py-6 sm:grid-cols-[9rem_1fr] sm:gap-6"
                    :style="{ '--rv-i': index }"
                    data-testid="update-row"
                >
                    <div
                        class="flex items-center gap-3 sm:flex-col sm:items-start sm:gap-1.5"
                    >
                        <time
                            :datetime="update.date"
                            class="font-mono text-xs text-muted-foreground tabular-nums"
                            >{{ update.label }}</time
                        >
                        <span
                            class="rounded-full border border-border/80 px-2 py-0.5 font-mono text-[10px] tracking-wider text-brand uppercase"
                            >{{ update.tag }}</span
                        >
                    </div>
                    <div>
                        <h3
                            class="text-base font-semibold tracking-tight text-foreground sm:text-lg"
                        >
                            {{ update.title }}
                        </h3>
                        <p
                            class="mt-1 max-w-xl text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ update.summary }}
                        </p>
                    </div>
                </li>
            </RevealGroup>
        </div>
    </section>
</template>
