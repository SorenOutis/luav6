<script setup lang="ts">
import { computed, ref, useSlots } from 'vue';

interface Props {
    mascot?: string;
    message?: string;
    label?: string;
    size?: number;
    compact?: boolean;
    initiallyOpen?: boolean;
    showMessage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    mascot: 'welcome',
    message: '',
    label: 'Show fox message',
    size: 150,
    compact: false,
    initiallyOpen: true,
    showMessage: true,
});

const slots = useSlots();
const webpFailed = ref(false);
const isMessageVisible = ref(props.initiallyOpen);

const src = computed(() =>
    webpFailed.value
        ? `/images/mascots/fox-${props.mascot}.png`
        : `/images/mascots/fox-${props.mascot}.webp`,
);

const toggleMessage = (): void => {
    isMessageVisible.value = !isMessageVisible.value;
};
</script>

<template>
    <div
        class="fox-companion relative flex items-center gap-3"
        :class="
            slots.default
                ? 'w-full max-w-none'
                : compact
                  ? 'max-w-[300px]'
                  : 'max-w-[420px]'
        "
        role="group"
        :aria-label="label"
    >
        <button
            v-if="showMessage"
            type="button"
            class="fox-companion__button shrink-0 rounded-full transition-transform duration-150 outline-none focus-visible:ring-2 focus-visible:ring-[#D97757] focus-visible:ring-offset-2"
            :aria-label="isMessageVisible ? 'Hide fox message' : label"
            :aria-expanded="isMessageVisible"
            @click="toggleMessage"
        >
            <img
                :src="src"
                :alt="label"
                :width="size"
                :height="size"
                class="fox-companion__image h-auto w-auto object-contain select-none"
                :style="{ width: `${size}px`, maxWidth: '32vw' }"
                draggable="false"
                decoding="async"
                @error="webpFailed = true"
            />
        </button>

        <div v-else class="shrink-0">
            <img
                :src="src"
                :alt="label"
                :width="size"
                :height="size"
                class="fox-companion__image h-auto w-auto object-contain select-none"
                :style="{ width: `${size}px`, maxWidth: '32vw' }"
                draggable="false"
                decoding="async"
                @error="webpFailed = true"
            />
        </div>

        <div
            v-if="$slots.default"
            class="fox-companion__content min-w-0 flex-1"
        >
            <slot />
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 scale-[0.97] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition duration-125 ease-out"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="translate-y-1 scale-[0.97] opacity-0"
        >
            <p
                v-if="showMessage && isMessageVisible"
                class="fox-companion__bubble relative rounded-2xl border border-[#D97757]/20 bg-card px-3.5 py-2.5 text-xs leading-relaxed text-foreground shadow-sm sm:text-sm"
                :class="compact ? 'w-[180px]' : 'w-[220px]'"
            >
                {{ message }}
            </p>
        </Transition>
    </div>
</template>

<style scoped>
.fox-companion__image {
    animation: fox-companion-float 4.5s ease-in-out infinite;
}

@keyframes fox-companion-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

@media (hover: hover) and (pointer: fine) {
    .fox-companion__button:hover {
        transform: translateY(-2px) scale(1.02);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fox-companion__image {
        animation: none;
    }
}
</style>
