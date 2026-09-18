<script setup lang="ts">
import {
    LayoutDashboard,
    BookOpen,
    Activity,
    ClipboardCheck,
    ArrowRight,
} from 'lucide-vue-next';

const quickLinks = [
    { label: 'Essay Rubric View', href: '#', icon: BookOpen },
    { label: 'Scoring Analytics', href: '#', icon: Activity },
    { label: 'Feedback Trace Log', href: '#', icon: ClipboardCheck },
];
</script>

<template>
    <section
        class="pulse-panel group/env relative flex flex-col overflow-hidden rounded-2xl border border-border/30 bg-card/60 p-6 backdrop-blur-2xl sm:p-8 lg:p-10 dark:border-border/15 dark:bg-background/50"
    >
        <div
            class="pointer-events-none absolute -top-20 -right-20 h-60 w-60 rounded-full bg-primary/5 blur-3xl transition-transform duration-1000 group-hover/env:scale-150 dark:bg-primary/10"
        ></div>

        <div class="relative z-10 mb-8">
            <div class="mb-2 flex items-center gap-2">
                <LayoutDashboard class="h-3 w-3 text-primary/60" />
                <p
                    class="text-[10px] font-black tracking-[0.25em] text-primary/80 uppercase"
                >
                    Review Center
                </p>
            </div>
            <h3 class="text-xl font-black tracking-tight uppercase sm:text-2xl">
                Essay Review <br />
                Controls
            </h3>
            <p
                class="mt-4 max-w-[280px] text-xs leading-relaxed text-muted-foreground/80"
            >
                Show how the AI scores essays, which rubric areas were checked,
                and where feedback comes from.
            </p>
        </div>

        <div class="relative z-10 mt-auto space-y-3">
            <a
                v-for="quickLink in quickLinks"
                :key="quickLink.label"
                :href="quickLink.href"
                class="quick-link-row group/link relative flex items-center justify-between overflow-hidden rounded-xl border border-border/20 px-5 py-4 transition-all hover:border-primary/30 hover:bg-muted/40 dark:border-border/10 dark:hover:bg-foreground/[0.06]"
            >
                <div
                    class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent transition-transform duration-700 group-hover/link:translate-x-full"
                ></div>

                <span class="relative z-10 flex items-center gap-4">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/30 transition-colors group-hover/link:bg-primary/10"
                    >
                        <component
                            :is="quickLink.icon"
                            class="h-4 w-4 text-muted-foreground transition-all duration-300 group-hover/link:text-primary"
                        />
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="text-[10px] font-black tracking-[0.18em] text-foreground/80 uppercase transition-colors group-hover/link:text-foreground"
                            >{{ quickLink.label }}</span
                        >
                        <span
                            class="text-[7px] font-bold tracking-widest text-muted-foreground/40 uppercase transition-colors group-hover/link:text-primary/40"
                            >Review_Enabled</span
                        >
                    </div>
                </span>
                <div class="flex items-center gap-2">
                    <div
                        class="h-1 w-1 animate-pulse rounded-full bg-primary opacity-0 transition-opacity group-hover/link:opacity-100"
                    ></div>
                    <ArrowRight
                        class="h-4 w-4 text-muted-foreground/40 transition-all group-hover/link:translate-x-1 group-hover/link:text-primary"
                    />
                </div>
            </a>
        </div>

        <div
            class="mt-8 flex items-center justify-between border-t border-border/10 pt-4"
        >
            <span
                class="text-[8px] font-black tracking-widest text-muted-foreground/40 uppercase"
                >Feedback_Traceable</span
            >
            <div class="flex gap-1">
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-1 w-4 rounded-full bg-emerald-500/20 transition-colors group-hover/env:bg-emerald-500/40"
                ></div>
            </div>
        </div>
    </section>
</template>
