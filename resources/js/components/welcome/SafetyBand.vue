<script setup lang="ts">
import {
    Check,
    FileClock,
    KeyRound,
    ScrollText,
    Sparkles,
    UserCheck,
    UsersRound,
} from 'lucide-vue-next';
import MiniDemo from '@/components/welcome/MiniDemo.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

const steps = [
    {
        label: 'Echo drafts',
        code: 'ai.draft',
        detail: 'Questions, grades, or feedback',
        icon: Sparkles,
    },
    {
        label: 'Pending action',
        code: 'queue.pending',
        detail: 'Queued with a one-time nonce',
        icon: FileClock,
    },
    {
        label: 'Teacher approves',
        code: 'teacher.approve',
        detail: 'Edit, approve, or discard',
        icon: UserCheck,
    },
    {
        label: 'Learner receives',
        code: 'publish',
        detail: 'Only approved work ships',
        icon: UsersRound,
    },
];

const guarantees = [
    {
        title: 'Nothing writes on its own',
        body: 'AI output is always a draft. There is no autonomous path from Echo to a learner.',
        icon: Check,
    },
    {
        title: 'Nonce-protected approvals',
        body: 'Every approval endpoint checks a single-use nonce, so a draft cannot be replayed or forged.',
        icon: KeyRound,
    },
    {
        title: 'Every AI call is logged',
        body: 'Prompts, tools, and costs land in the workspace audit log against its AI budget.',
        icon: ScrollText,
    },
];

/** Phase n lights steps 0…n; the last phase holds the finished flow. */
const STEP_DURATIONS = [1400, 1400, 1600, 2600];
</script>

<template>
    <section
        id="safety"
        class="safety-band relative left-1/2 w-screen -translate-x-1/2 scroll-mt-24 overflow-hidden border-y border-white/10 bg-zinc-950 py-24 text-zinc-50 sm:py-32"
        aria-labelledby="safety-heading"
    >
        <div
            class="safety-glow pointer-events-none absolute inset-0"
            aria-hidden="true"
        ></div>

        <div
            class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 xl:max-w-7xl"
        >
            <RevealGroup class="max-w-3xl">
                <p
                    class="rv flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-zinc-400 uppercase"
                    :style="{ '--rv-i': 0 }"
                >
                    <span class="text-brand">04</span>
                    <span
                        class="h-px w-8 bg-white/20"
                        aria-hidden="true"
                    ></span>
                    Human in the loop
                </p>
                <h2
                    id="safety-heading"
                    class="rv mt-4 font-sans text-3xl font-semibold tracking-[-0.035em] text-balance text-white sm:text-4xl lg:text-5xl"
                    :style="{ '--rv-i': 1 }"
                >
                    AI drafts. Teachers decide.
                </h2>
                <p
                    class="rv mt-4 max-w-xl text-sm leading-relaxed text-zinc-400 sm:text-base"
                    :style="{ '--rv-i': 2 }"
                >
                    The approval boundary isn’t a setting you can forget to turn
                    on. It’s how LSI is built.
                </p>
            </RevealGroup>

            <RevealGroup class="mt-12 sm:mt-16">
                <div class="rv" :style="{ '--rv-i': 0 }">
                    <MiniDemo
                        v-slot="{ phase }"
                        :durations="STEP_DURATIONS"
                        :enabled="!prefersReducedMotion"
                        :static-phase="3"
                        data-testid="safety-flow"
                    >
                        <ol
                            class="safety-flow relative grid gap-4 md:grid-cols-4 md:gap-0"
                            :style="{
                                '--progress': phase / (steps.length - 1),
                            }"
                        >
                            <span
                                class="safety-rail pointer-events-none absolute top-6 right-[12.5%] left-[12.5%] hidden h-px bg-white/10 md:block"
                                aria-hidden="true"
                            >
                                <span
                                    class="safety-rail-fill absolute inset-y-0 left-0 bg-brand"
                                ></span>
                            </span>

                            <li
                                v-for="(step, index) in steps"
                                :key="step.code"
                                class="safety-step relative flex items-start gap-4 md:flex-col md:items-center md:px-4 md:text-center"
                                :class="{
                                    'is-on': index <= phase,
                                    'is-current': index === phase,
                                    'is-gate': index === 2,
                                }"
                            >
                                <span
                                    class="safety-node relative z-10 flex h-12 w-12 shrink-0 items-center justify-center rounded-full border"
                                >
                                    <component
                                        :is="step.icon"
                                        class="h-5 w-5"
                                        stroke-width="1.6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div class="md:mt-4">
                                    <p
                                        class="font-mono text-[10px] tracking-wider text-zinc-500 uppercase"
                                    >
                                        {{ step.code }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-white"
                                    >
                                        {{ step.label }}
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-400">
                                        {{ step.detail }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </MiniDemo>
                </div>
            </RevealGroup>

            <RevealGroup
                :stagger="90"
                class="mt-14 grid gap-px overflow-hidden rounded-3xl border border-white/10 bg-white/10 sm:mt-20 md:grid-cols-3"
            >
                <article
                    v-for="(guarantee, index) in guarantees"
                    :key="guarantee.title"
                    class="rv flex flex-col gap-3 bg-zinc-950 p-6 sm:p-8"
                    :style="{ '--rv-i': index }"
                >
                    <component
                        :is="guarantee.icon"
                        class="h-5 w-5 text-brand"
                        stroke-width="1.6"
                        aria-hidden="true"
                    />
                    <h3
                        class="text-base font-semibold tracking-tight text-white"
                    >
                        {{ guarantee.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-zinc-400">
                        {{ guarantee.body }}
                    </p>
                </article>
            </RevealGroup>
        </div>
    </section>
</template>

<style scoped>
.safety-glow {
    background:
        radial-gradient(
            60% 50% at 50% 0%,
            color-mix(in oklab, var(--color-brand) 22%, transparent),
            transparent 70%
        ),
        linear-gradient(to right, rgb(255 255 255 / 0.04) 1px, transparent 1px)
            0 0 / 64px 64px,
        linear-gradient(to bottom, rgb(255 255 255 / 0.04) 1px, transparent 1px)
            0 0 / 64px 64px;
    mask-image: radial-gradient(80% 70% at 50% 30%, #000 30%, transparent 100%);
}

.safety-rail-fill {
    width: calc(var(--progress, 1) * 100%);
    transition: width 0.9s var(--ease-out-expo);
}

.safety-node {
    border-color: rgb(255 255 255 / 0.14);
    background-color: rgb(24 24 27);
    color: rgb(161 161 170);
    transition:
        border-color 0.4s ease,
        background-color 0.4s ease,
        color 0.4s ease,
        box-shadow 0.4s ease;
}

.safety-step.is-on .safety-node {
    border-color: color-mix(in oklab, var(--color-brand) 60%, transparent);
    color: #fff;
}

.safety-step.is-gate.is-on .safety-node {
    background-color: var(--color-brand);
    border-color: var(--color-brand);
}

.safety-step.is-current .safety-node {
    box-shadow: 0 0 0 6px
        color-mix(in oklab, var(--color-brand) 20%, transparent);
}

:global(html[data-low-end]) .safety-glow {
    display: none;
}

@media (prefers-reduced-motion: reduce) {
    .safety-rail-fill,
    .safety-node {
        transition: none;
    }
}
</style>
