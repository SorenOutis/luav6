<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMobile } from '@/composables/useMobile';

const props = withDefaults(
    defineProps<{
        as?: string;
        stagger?: number;
    }>(),
    { as: 'div', stagger: 80 },
);

const { prefersReducedMotion, isLowEndDevice } = useMobile();
const motionAllowed = computed(
    () => !prefersReducedMotion.value && !isLowEndDevice.value,
);

const isRevealing = ref(false);
const root = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | undefined;
let settleTimer: number | undefined;

onMounted(() => {
    if (
        !motionAllowed.value ||
        typeof IntersectionObserver === 'undefined' ||
        !root.value
    ) {
        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            if (!entry.isIntersecting || isRevealing.value) {
                return;
            }

            isRevealing.value = true;
            observer?.disconnect();

            const count = root.value?.querySelectorAll('.rv').length ?? 0;
            settleTimer = window.setTimeout(
                () => {
                    isRevealing.value = false;
                },
                count * props.stagger + 900,
            );
        },
        { threshold: 0.15, rootMargin: '0px 0px -60px 0px' },
    );
    observer.observe(root.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();

    if (settleTimer !== undefined) {
        window.clearTimeout(settleTimer);
    }
});
</script>

<template>
    <component
        :is="as"
        ref="root"
        class="rv-group"
        :class="{ 'is-revealing': isRevealing }"
        :style="{ '--rv-stagger': `${stagger}ms` }"
    >
        <slot />
    </component>
</template>
