<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

const quotes = [
    {
        body: 'LSI cut our grading turnaround by half. Students get feedback while the lesson is still fresh, and I still approve every AI draft.',
        name: 'Maria Santos',
        role: 'Grade 8 Mathematics Head',
        initials: 'MS',
    },
    {
        body: 'I can see exactly which item I missed and why before the next class. The streak keeps me practising on weekends.',
        name: 'Jun Pascual',
        role: 'Grade 8 Learner',
        initials: 'JP',
    },
    {
        body: 'One workspace, every section, and an audit trail I can show the board. The AI budget means no surprise bills.',
        name: 'Dr. Liza Ramos',
        role: 'School Principal',
        initials: 'LR',
    },
    {
        body: 'The Library Hub replaced our printed reviewers. Teachers share once and every section has it in seconds.',
        name: 'Paolo Dizon',
        role: 'Science Coordinator',
        initials: 'PD',
    },
];

/**
 * The marquee duplicates the list so the loop is seamless. The copy is
 * aria-hidden, and it is skipped entirely when motion is off — the track
 * then becomes a static, horizontally scrollable row.
 */
const isAnimated = computed(() => !props.prefersReducedMotion);
</script>

<template>
    <section
        aria-label="What teachers, learners, and schools say"
        class="welcome-voices relative mt-14 sm:mt-20"
    >
        <div
            class="voices-mask"
            :class="isAnimated ? 'overflow-hidden' : 'overflow-x-auto'"
        >
            <ul
                class="voices-track flex w-max gap-4 py-2"
                :class="{ 'is-animated': isAnimated }"
                data-testid="voices-track"
            >
                <template v-for="copy in isAnimated ? 2 : 1" :key="copy">
                    <li
                        v-for="quote in quotes"
                        :key="`${copy}-${quote.name}`"
                        class="flex w-[19rem] shrink-0 flex-col justify-between gap-6 rounded-3xl border border-border/80 bg-card p-6 sm:w-[24rem] sm:p-7"
                        :aria-hidden="copy === 2 ? 'true' : undefined"
                    >
                        <blockquote
                            class="text-sm leading-relaxed text-foreground sm:text-base"
                        >
                            “{{ quote.body }}”
                        </blockquote>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/15 text-xs font-bold text-brand"
                                aria-hidden="true"
                                >{{ quote.initials }}</span
                            >
                            <div>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ quote.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ quote.role }}
                                </p>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.voices-mask {
    mask-image: linear-gradient(
        to right,
        transparent,
        #000 6%,
        #000 94%,
        transparent
    );
}

.voices-track.is-animated {
    animation: voices-scroll 60s linear infinite;
}

.voices-track.is-animated:hover,
.voices-track.is-animated:focus-within {
    animation-play-state: paused;
}

@keyframes voices-scroll {
    to {
        transform: translateX(calc(-50% - 0.5rem));
    }
}

:global(html[data-low-end]) .voices-track.is-animated {
    animation: none !important;
}

@media (prefers-reduced-motion: reduce) {
    .voices-track.is-animated {
        animation: none;
    }
}
</style>
