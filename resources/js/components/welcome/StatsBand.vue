<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

type Stat = {
    value: number;
    prefix?: string;
    suffix: string;
    label: string;
    isBrand?: boolean;
};

const stats: Stat[] = [
    {
        value: 50,
        suffix: '%+',
        label: 'Less time spent grading objective items',
    },
    {
        value: 2,
        prefix: '< ',
        suffix: ' hrs',
        label: 'From submission to feedback',
    },
    {
        value: 100,
        suffix: '%',
        label: 'Of AI drafts reviewed by a teacher',
        isBrand: true,
    },
    { value: 0, suffix: '', label: 'Autonomous writes to learner records' },
];

const COUNT_MS = 1400;

/**
 * Rendered numbers. They rest on the final values so SSR, no-JS, and
 * reduced-motion visitors see the real figures; the count-up only arms
 * when IntersectionObserver exists and motion is allowed.
 */
const displayed = ref(stats.map((stat) => stat.value));
const root = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | undefined;
let frame: number | undefined;

const easeOutExpo = (t: number) => (t >= 1 ? 1 : 1 - Math.pow(2, -10 * t));

const countUp = () => {
    const startedAt = performance.now();

    const tick = (now: number) => {
        const t = Math.min((now - startedAt) / COUNT_MS, 1);
        const eased = easeOutExpo(t);
        displayed.value = stats.map((stat) => Math.round(stat.value * eased));

        frame = t < 1 ? window.requestAnimationFrame(tick) : undefined;
    };

    frame = window.requestAnimationFrame(tick);
};

onMounted(() => {
    if (
        props.prefersReducedMotion ||
        typeof IntersectionObserver === 'undefined' ||
        !root.value
    ) {
        return;
    }

    displayed.value = stats.map(() => 0);

    observer = new IntersectionObserver(
        ([entry]) => {
            if (!entry.isIntersecting) {
                return;
            }

            observer?.disconnect();
            countUp();
        },
        { threshold: 0.4 },
    );
    observer.observe(root.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();

    if (frame !== undefined) {
        window.cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <section
        ref="root"
        aria-labelledby="proof-heading"
        class="welcome-proof pt-20 sm:pt-28"
    >
        <RevealGroup class="mx-auto max-w-6xl px-4 sm:px-6 xl:max-w-7xl">
            <p
                class="rv flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-muted-foreground uppercase"
                :style="{ '--rv-i': 0 }"
            >
                <span class="text-brand">05</span>
                <span class="h-px w-8 bg-border" aria-hidden="true"></span>
                In classrooms
            </p>
            <h2
                id="proof-heading"
                class="rv mt-4 max-w-2xl font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl"
                :style="{ '--rv-i': 1 }"
            >
                Faster feedback, without giving up control.
            </h2>
        </RevealGroup>

        <RevealGroup
            :stagger="90"
            as="dl"
            class="mx-auto mt-10 grid max-w-6xl grid-cols-2 border-y border-border/70 sm:mt-14 lg:grid-cols-4 xl:max-w-7xl"
        >
            <div
                v-for="(stat, index) in stats"
                :key="stat.label"
                class="rv rv-pop flex flex-col-reverse gap-2 px-4 py-8 sm:px-6 sm:py-10"
                :class="{
                    'border-l border-border/70': index % 2 === 1,
                    'lg:border-l': index > 0,
                    'border-t border-border/70 lg:border-t-0': index > 1,
                }"
                :style="{ '--rv-i': index }"
            >
                <dt
                    class="max-w-[22ch] text-xs leading-relaxed text-muted-foreground sm:text-sm"
                >
                    {{ stat.label }}
                </dt>
                <dd
                    class="font-sans text-4xl font-semibold tracking-[-0.045em] tabular-nums sm:text-5xl lg:text-6xl"
                    :class="stat.isBrand ? 'text-brand' : 'text-foreground'"
                    data-testid="stat-value"
                >
                    {{ stat.prefix ?? '' }}{{ displayed[index]
                    }}{{ stat.suffix }}
                </dd>
            </div>
        </RevealGroup>
    </section>
</template>
