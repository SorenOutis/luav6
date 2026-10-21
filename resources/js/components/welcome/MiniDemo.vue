<script setup lang="ts">
import { ref, watch } from 'vue';
import { useDemoLoop, useInView } from '@/composables/useDemoLoop';

const props = withDefaults(
    defineProps<{
        durations: number[];
        enabled?: boolean;
        staticPhase?: number;
    }>(),
    {
        enabled: true,
        staticPhase: 0,
    },
);

/**
 * Wraps a looping phase-driven mini-demo: exposes the current `phase`
 * through the default slot, pauses off-screen, and parks on
 * `staticPhase` when looping is disabled (reduced motion / low-end).
 */
const root = ref<HTMLElement | null>(null);
const inView = useInView(root);
const { phase, cycle, start, stop, set } = useDemoLoop(props.durations);

watch(
    [inView, () => props.enabled, () => props.staticPhase],
    ([visible, on, parked]) => {
        if (!on) {
            stop();
            set(parked);
            return;
        }

        if (visible) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div ref="root" :data-phase="phase">
        <slot :phase="phase" :cycle="cycle" />
    </div>
</template>
