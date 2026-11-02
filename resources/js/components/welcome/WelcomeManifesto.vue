<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

const MANIFESTO =
    'Every assessment should inform tomorrow’s lesson. Echo drafts the questions, the scores, and the feedback — and a teacher approves every word before it reaches a learner.';

/** Words that pick up the brand colour once they are lit. */
const EMPHASIS = new Set(['tomorrow’s', 'lesson.', 'teacher', 'approves']);

const words = computed(() =>
    MANIFESTO.split(' ').map((text, index) => ({
        text,
        index,
        isEmphasis: EMPHASIS.has(text),
    })),
);

/**
 * Scroll progress through the manifesto, 0 → 1. It is written to a single
 * CSS variable so each frame costs one style write; every word derives its
 * own opacity from `--lit` and its `--w-i` index in CSS. The resting value is
 * 1 so SSR, no-JS, and reduced-motion visitors read the fully lit sentence.
 */
const progress = ref(1);
const root = ref<HTMLElement | null>(null);
let frame: number | undefined;

const measure = () => {
    frame = undefined;
    const el = root.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const viewport = window.innerHeight || 1;
    const start = viewport * 0.85;
    const end = viewport * 0.35;
    const raw = (start - rect.top) / (start - end + rect.height * 0.5);

    progress.value = Math.min(Math.max(raw, 0), 1);
};

const onScroll = () => {
    if (frame === undefined) {
        frame = window.requestAnimationFrame(measure);
    }
};

onMounted(() => {
    if (props.prefersReducedMotion || typeof window === 'undefined') {
        return;
    }

    measure();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);

    if (frame !== undefined) {
        window.cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <section
        id="manifesto"
        ref="root"
        aria-labelledby="manifesto-heading"
        class="welcome-manifesto relative scroll-mt-24 py-24 sm:py-32 lg:py-40"
        :style="{ '--lit': progress, '--words': words.length }"
        data-testid="welcome-manifesto"
    >
        <div class="mx-auto max-w-6xl xl:max-w-7xl">
            <p
                class="flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-muted-foreground uppercase"
            >
                <span class="text-brand">01</span>
                <span class="h-px w-8 bg-border" aria-hidden="true"></span>
                Why we build LSI
            </p>

            <h2
                id="manifesto-heading"
                class="mt-8 font-sans text-3xl leading-[1.12] font-medium tracking-[-0.035em] text-balance text-foreground sm:text-5xl md:text-6xl lg:text-[4.5rem]"
            >
                <span class="sr-only">{{ MANIFESTO }}</span>
                <span aria-hidden="true" class="inline">
                    <template v-for="word in words" :key="word.index">
                        <span
                            class="mf-word inline-block"
                            :class="{ 'mf-emphasis': word.isEmphasis }"
                            :style="{ '--w-i': word.index }"
                            >{{ word.text }}</span
                        >{{ ' ' }}
                    </template>
                    <span
                        class="ml-[0.1em] inline-flex align-middle text-[0.45em]"
                    >
                        <ChatAiOrb
                            size="inline"
                            state="micro"
                            :delay="900"
                            color="var(--color-brand)"
                        />
                    </span>
                </span>
            </h2>
        </div>
    </section>
</template>

<style scoped>
/*
 * Each word lights up as the reader scrolls past it: its opacity ramps from
 * 0.16 to 1 across a one-word window centred on its position in the sentence.
 */
.mf-word {
    opacity: clamp(
        0.16,
        calc(var(--lit, 1) * (var(--words) + 2) - var(--w-i)),
        1
    );
}

.mf-emphasis {
    color: color-mix(
        in oklab,
        var(--color-brand)
            calc(
                clamp(0, var(--lit, 1) * (var(--words) + 2) - var(--w-i), 1) *
                    100%
            ),
        var(--color-foreground)
    );
}
</style>
