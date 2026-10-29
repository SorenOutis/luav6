<script setup lang="ts">
import {
    BookOpen,
    Flame,
    Gauge,
    Layers,
    ScrollText,
    Sparkles,
    Trophy,
} from 'lucide-vue-next';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

const libraryFiles = [
    { name: 'Quadratics reviewer.pdf', meta: 'Grade 8 · 2.1 MB' },
    { name: 'Lab safety slides.pptx', meta: 'Grade 7 · 6.4 MB' },
    { name: 'Essay rubric.docx', meta: 'English · 84 KB' },
];

const leaderboard = [
    { rank: 1, name: 'Althea R.', xp: '2,480' },
    { rank: 2, name: 'Jun P.', xp: '2,315' },
    { rank: 3, name: 'Maria S.', xp: '2,190' },
    { rank: 4, name: 'Paolo D.', xp: '1,975' },
];

/** Four weeks of practice: 1 = active day, 0 = missed day. */
const streakDays = [
    1, 1, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1,
    1, 1, 1,
];

const auditLines = [
    { time: '09:14', text: 'echo.draft  quiz#218', tone: 'muted' },
    { time: '09:16', text: 'teacher.approve  nonce✓', tone: 'brand' },
    { time: '09:16', text: 'publish  section:diamond', tone: 'muted' },
    { time: '10:02', text: 'grade.release  32 learners', tone: 'muted' },
];

const workspaces = ['St. Mary’s HS', 'Rizal Academy', 'Northfield'];
</script>

<template>
    <section
        id="capabilities"
        class="welcome-capabilities scroll-mt-32 border-b border-border/60 py-20 sm:py-28"
        aria-labelledby="capabilities-heading"
    >
        <RevealGroup
            class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-end lg:justify-between xl:max-w-7xl"
        >
            <div class="max-w-2xl">
                <p
                    class="rv flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-muted-foreground uppercase"
                    :style="{ '--rv-i': 0 }"
                >
                    <span class="text-brand">03</span>
                    <span class="h-px w-8 bg-border" aria-hidden="true"></span>
                    Capabilities
                </p>
                <h2
                    id="capabilities-heading"
                    class="rv mt-4 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl"
                    :style="{ '--rv-i': 1 }"
                >
                    Everything a school runs on, in one workspace.
                </h2>
            </div>
            <p
                class="rv max-w-sm text-sm leading-relaxed text-muted-foreground sm:text-base"
                :style="{ '--rv-i': 2 }"
            >
                Materials, motivation, and governance sit next to the assessment
                loop, so nothing gets lost between tools.
            </p>
        </RevealGroup>

        <RevealGroup
            :stagger="70"
            class="bento-grid mx-auto mt-10 grid max-w-6xl gap-px overflow-hidden rounded-3xl border border-border/80 bg-border/70 sm:mt-14 md:grid-cols-6 xl:max-w-7xl"
        >
            <!-- Library Hub -->
            <article
                class="rv bento-tile group md:col-span-4"
                :style="{ '--rv-i': 0 }"
            >
                <header class="bento-head">
                    <BookOpen class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">Library Hub</h3>
                </header>
                <p class="bento-copy">
                    Share reviewers, slides, and rubrics per section — previewed
                    in the browser, no printing required.
                </p>
                <ul class="mt-6 space-y-2" aria-label="Library preview">
                    <li
                        v-for="(file, index) in libraryFiles"
                        :key="file.name"
                        class="bento-file flex items-center justify-between rounded-xl border border-border/70 bg-background/60 px-3.5 py-2.5"
                        :style="{ '--i': index }"
                    >
                        <span class="text-xs font-medium text-foreground">{{
                            file.name
                        }}</span>
                        <span
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ file.meta }}</span
                        >
                    </li>
                </ul>
            </article>

            <!-- XP & levels -->
            <article
                class="rv bento-tile group md:col-span-2"
                :style="{ '--rv-i': 1 }"
            >
                <header class="bento-head">
                    <Sparkles class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">XP &amp; levels</h3>
                </header>
                <p class="bento-copy">
                    Every finished quiz earns XP, so practice feels like
                    progress.
                </p>
                <div class="mt-auto pt-6">
                    <div class="flex items-baseline justify-between">
                        <span
                            class="font-sans text-4xl font-semibold tracking-[-0.04em] text-foreground tabular-nums"
                            >Lv 12</span
                        >
                        <span class="font-mono text-[11px] text-brand"
                            >+120 XP</span
                        >
                    </div>
                    <div
                        class="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary"
                        role="img"
                        aria-label="Level progress 72 percent"
                    >
                        <div
                            class="bento-xp h-full w-[72%] rounded-full bg-brand"
                        ></div>
                    </div>
                </div>
            </article>

            <!-- Leaderboard -->
            <article
                class="rv bento-tile group md:col-span-2"
                :style="{ '--rv-i': 2 }"
            >
                <header class="bento-head">
                    <Trophy class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">Section leaderboards</h3>
                </header>
                <p class="bento-copy">
                    Friendly competition scoped to each section — never across
                    schools.
                </p>
                <ol class="mt-6 space-y-1.5">
                    <li
                        v-for="row in leaderboard"
                        :key="row.rank"
                        class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-xs"
                        :class="
                            row.rank === 1
                                ? 'bg-brand/10 text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        <span class="w-4 font-mono tabular-nums">{{
                            row.rank
                        }}</span>
                        <span class="flex-1 font-medium text-foreground">{{
                            row.name
                        }}</span>
                        <span class="font-mono tabular-nums">{{ row.xp }}</span>
                    </li>
                </ol>
            </article>

            <!-- Streaks -->
            <article
                class="rv bento-tile group md:col-span-2"
                :style="{ '--rv-i': 3 }"
            >
                <header class="bento-head">
                    <Flame class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">Streak calendar</h3>
                </header>
                <p class="bento-copy">Daily habits, visible at a glance.</p>
                <div
                    class="mt-auto grid grid-cols-7 gap-1.5 pt-6"
                    role="img"
                    aria-label="Four-week practice streak"
                >
                    <span
                        v-for="(day, index) in streakDays"
                        :key="index"
                        class="aspect-square rounded-[5px]"
                        :class="day ? 'bg-brand/80' : 'bg-secondary'"
                    ></span>
                </div>
            </article>

            <!-- Audit trail -->
            <article
                class="rv bento-tile group md:col-span-2"
                :style="{ '--rv-i': 4 }"
            >
                <header class="bento-head">
                    <ScrollText class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">Audit trail</h3>
                </header>
                <p class="bento-copy">Who did what, and when. Always.</p>
                <div
                    class="mt-auto space-y-1 rounded-xl border border-border/70 bg-background/60 p-3 pt-3 font-mono text-[10px] leading-relaxed"
                >
                    <p
                        v-for="line in auditLines"
                        :key="line.time + line.text"
                        class="flex gap-2"
                    >
                        <span class="text-muted-foreground">{{
                            line.time
                        }}</span>
                        <span
                            :class="
                                line.tone === 'brand'
                                    ? 'text-brand'
                                    : 'text-foreground/80'
                            "
                            >{{ line.text }}</span
                        >
                    </p>
                </div>
            </article>

            <!-- Workspace isolation -->
            <article
                class="rv bento-tile group md:col-span-3"
                :style="{ '--rv-i': 5 }"
            >
                <header class="bento-head">
                    <Layers class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">Workspace isolation</h3>
                </header>
                <p class="bento-copy">
                    Each school is its own tenant. Teachers see their sections,
                    learners see their courses — nothing leaks between them.
                </p>
                <div class="mt-6 grid grid-cols-3 gap-2">
                    <div
                        v-for="(workspace, index) in workspaces"
                        :key="workspace"
                        class="bento-tenant rounded-xl border border-dashed px-3 py-3 text-center"
                        :class="
                            index === 0
                                ? 'border-brand/50 bg-brand/5'
                                : 'border-border/80'
                        "
                    >
                        <p
                            class="truncate text-[11px] font-medium text-foreground"
                        >
                            {{ workspace }}
                        </p>
                        <p
                            class="mt-0.5 font-mono text-[9px] text-muted-foreground"
                        >
                            {{ index === 0 ? 'your workspace' : 'isolated' }}
                        </p>
                    </div>
                </div>
            </article>

            <!-- AI budgets -->
            <article
                class="rv bento-tile group md:col-span-3"
                :style="{ '--rv-i': 6 }"
            >
                <header class="bento-head">
                    <Gauge class="h-4 w-4 text-brand" aria-hidden="true" />
                    <h3 class="bento-title">AI budgets</h3>
                </header>
                <p class="bento-copy">
                    Set a monthly AI allowance per workspace. Every Echo call is
                    metered and logged.
                </p>
                <div class="mt-6">
                    <div
                        class="flex items-center justify-between font-mono text-[10px] text-muted-foreground"
                    >
                        <span>October usage</span>
                        <span class="text-foreground tabular-nums"
                            >61% of budget</span
                        >
                    </div>
                    <div
                        class="mt-2 flex h-2 gap-0.5 overflow-hidden rounded-full"
                        role="img"
                        aria-label="AI budget 61 percent used"
                    >
                        <span
                            v-for="segment in 20"
                            :key="segment"
                            class="flex-1"
                            :class="
                                segment <= 12 ? 'bg-brand/80' : 'bg-secondary'
                            "
                        ></span>
                    </div>
                </div>
            </article>
        </RevealGroup>
    </section>
</template>

<style scoped>
.bento-tile {
    display: flex;
    flex-direction: column;
    min-height: 15rem;
    background-color: var(--color-card);
    padding: 1.5rem;
    transition: background-color var(--dur-hover, 260ms) ease;
}

@media (min-width: 640px) {
    .bento-tile {
        padding: 1.75rem;
    }
}

.bento-tile:hover {
    background-color: color-mix(
        in oklab,
        var(--color-card) 94%,
        var(--color-brand)
    );
}

.bento-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bento-title {
    font-size: 0.9375rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--color-foreground);
}

.bento-copy {
    margin-top: 0.5rem;
    max-width: 42ch;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--color-muted-foreground);
}

.bento-file {
    transition: transform var(--dur-hover, 260ms) var(--ease-out-expo);
    transition-delay: calc(var(--i, 0) * 40ms);
}

.group:hover .bento-file {
    transform: translateX(4px);
}

.bento-tenant {
    transition: transform var(--dur-hover, 260ms) var(--ease-out-expo);
}

.group:hover .bento-tenant:first-child {
    transform: translateY(-3px);
}

@media (prefers-reduced-motion: reduce) {
    .bento-file,
    .bento-tenant {
        transition: none;
    }
}
</style>
