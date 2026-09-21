<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    ListChecks,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    UserRound,
} from 'lucide-vue-next';

withDefaults(
    defineProps<{
        isCoarsePointer?: boolean;
        prefersReducedMotion?: boolean;
        auth?: { user: any };
        dashboard?: () => string;
        login?: () => string;
    }>(),
    {
        isCoarsePointer: false,
        prefersReducedMotion: false,
        auth: undefined,
        dashboard: undefined,
        login: undefined,
    },
);

const principles = [
    {
        icon: Check,
        title: 'Create assessments',
        description: 'Build quizzes and assignments that fit your class.',
        type: 'builder',
    },
    {
        icon: UserRound,
        title: 'Review responses',
        description:
            'See what learners understand before you decide what comes next.',
        type: 'review',
    },
    {
        icon: ShieldCheck,
        title: 'Plan next steps',
        description:
            'Give focused feedback and follow-up while learning is still happening.',
        type: 'action',
    },
];
</script>

<template>
    <section
        id="features"
        class="welcome-principles scroll-mt-32 border-b border-border/70 py-16 sm:py-20"
        :class="{ 'lite-motion': prefersReducedMotion }"
        aria-labelledby="principles-heading"
        data-lazy="true"
        style="content-visibility: auto; contain-intrinsic-size: 0 600px"
    >
        <div class="text-center">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-[#D97757] uppercase"
            >
                Designed for daily classrooms
            </p>
            <h2
                id="principles-heading"
                class="mt-3 font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
            >
                The work stays clear.
            </h2>
            <p
                class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base"
            >
                Every feature removes administrative friction so you can focus
                on the teaching moment.
            </p>
        </div>

        <div
            class="mt-12 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
        >
            <article
                v-for="(principle, index) in principles"
                :key="principle.title"
                class="flex flex-col justify-between p-6 transition-colors hover:bg-secondary/15 sm:p-8 lg:p-10"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-border/80 bg-card text-[#D97757] shadow-sm"
                        >
                            <component
                                :is="principle.icon"
                                class="h-5 w-5"
                                stroke-width="1.75"
                                aria-hidden="true"
                            />
                        </div>
                        <span class="font-mono text-xs text-muted-foreground">
                            0{{ index + 1 }}
                        </span>
                    </div>

                    <h3
                        class="mt-6 font-serif text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ principle.title }}
                    </h3>
                    <p
                        class="mt-2.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ principle.description }}
                    </p>
                </div>

                <!-- Tangible Micro-UI Preview -->
                <div
                    class="mt-8 rounded-xl border border-border/70 bg-card/90 p-4 shadow-sm"
                >
                    <!-- Builder Micro-UI -->
                    <template v-if="principle.type === 'builder'">
                        <div
                            class="flex items-center justify-between text-[11px] font-medium text-muted-foreground"
                        >
                            <span
                                class="flex items-center gap-1.5 font-semibold text-foreground"
                            >
                                <ListChecks
                                    class="h-3.5 w-3.5 text-[#D97757]"
                                />
                                Item 2 · Auto-scored
                            </span>
                            <span class="text-[#D97757]"
                                >Curriculum tagged</span
                            >
                        </div>
                        <div class="mt-3 space-y-1.5 text-xs">
                            <div
                                class="flex items-center justify-between rounded-md border border-border/60 bg-background/80 px-2.5 py-1.5 text-foreground"
                            >
                                <span>A. x = 2, x = 3</span>
                                <span class="text-[10px] text-muted-foreground"
                                    >Distractor</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1.5 font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                <span>B. x = 3, x = 4</span>
                                <CheckCircle2
                                    class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Review Micro-UI -->
                    <template v-else-if="principle.type === 'review'">
                        <div
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span
                                class="flex items-center gap-1 font-semibold text-[#D97757]"
                            >
                                <Sparkles class="h-3.5 w-3.5" />
                                AI Draft Ready
                            </span>
                            <span
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                1-Click Approve
                            </span>
                        </div>
                        <p
                            class="mt-2.5 text-[11px] leading-relaxed text-muted-foreground"
                        >
                            "Clear explanation of the factoring logic. Good
                            connection to roots."
                        </p>
                        <div
                            class="mt-3 flex items-center justify-between border-t border-border/50 pt-2 text-[10px] text-muted-foreground"
                        >
                            <span>Maria Santos (Gr 8)</span>
                            <span class="font-semibold text-foreground"
                                >Teacher Verified ✓</span
                            >
                        </div>
                    </template>

                    <!-- Action Micro-UI -->
                    <template v-else>
                        <div
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span
                                class="flex items-center gap-1 font-semibold text-foreground"
                            >
                                <TrendingUp
                                    class="h-3.5 w-3.5 text-[#D97757]"
                                />
                                Section Mastery: 88%
                            </span>
                            <span
                                class="text-[10px] font-semibold text-[#D97757]"
                                >Ready to advance</span
                            >
                        </div>
                        <div
                            class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full rounded-full bg-[#D97757]"
                                style="width: 88%"
                            ></div>
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between text-[10px] text-muted-foreground"
                        >
                            <span>Tomorrow: Quadratic Formula</span>
                            <span class="font-semibold text-[#D97757]"
                                >Auto-queued</span
                            >
                        </div>
                    </template>
                </div>
            </article>
        </div>
    </section>
</template>
