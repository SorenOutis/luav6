<script setup lang="ts">
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);
</script>

<template>
    <RevealGroup
        as="section"
        :stagger="90"
        aria-labelledby="statement-heading"
        class="relative"
    >
        <div class="relative mx-auto max-w-5xl xl:max-w-6xl">
            <!-- Echo perched on the card's top-right corner -->
            <div
                class="rv rv-pop pointer-events-none absolute -top-16 right-6 z-20 sm:-top-20 sm:right-10 lg:right-12"
                :style="{ '--rv-i': 2 }"
                aria-hidden="true"
            >
                <div :class="{ 'statement-orb': !prefersReducedMotion }">
                    <ChatAiOrb
                        size="md"
                        state="micro"
                        :delay="900"
                        color="var(--color-brand)"
                    />
                </div>
            </div>

            <div
                class="rv relative z-10 flex flex-col items-center gap-4 rounded-3xl border border-border/80 bg-card px-6 py-14 text-center shadow-xs sm:px-12 sm:py-16"
                :style="{ '--rv-i': 0 }"
            >
                <p
                    class="rv font-mono text-xs font-medium tracking-widest text-brand uppercase"
                    :style="{ '--rv-i': 1 }"
                >
                    Why we build LSI
                </p>
                <h2
                    id="statement-heading"
                    class="rv rv-down max-w-3xl font-sans text-3xl leading-tight font-medium tracking-[-0.02em] text-balance text-foreground sm:text-4xl md:text-5xl"
                    :style="{ '--rv-i': 2 }"
                >
                    Every assessment informs tomorrow’s lesson.
                </h2>
                <p
                    class="rv mt-1 max-w-[48ch] text-sm leading-relaxed text-muted-foreground sm:text-base"
                    :style="{ '--rv-i': 3 }"
                >
                    LSI keeps the loop tight — answer, score, feedback, reteach
                    — and every AI draft passes through a teacher before it
                    reaches a learner.
                </p>
            </div>
        </div>
    </RevealGroup>
</template>

<style scoped>
.statement-orb {
    animation: statement-orb-float 5.4s ease-in-out infinite alternate;
}

@keyframes statement-orb-float {
    from {
        transform: translateY(0);
    }
    to {
        transform: translateY(-7px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .statement-orb {
        animation: none;
    }
}
</style>
