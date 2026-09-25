<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Thinking',
    },
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5"
        role="status"
        :aria-label="`${label}…`"
    >
        <span>{{ label }}</span>
        <span class="inline-flex items-center gap-1" aria-hidden="true">
            <span class="thinking-dot h-1.5 w-1.5 shrink-0 rounded-full" />
            <span class="thinking-dot h-1.5 w-1.5 shrink-0 rounded-full" />
            <span class="thinking-dot h-1.5 w-1.5 shrink-0 rounded-full" />
        </span>
    </span>
</template>

<style scoped>
@keyframes thinking-bounce {
    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.35;
    }
    40% {
        transform: translateY(-3.5px);
        opacity: 1;
    }
}

.thinking-dot {
    display: inline-block;
    background-color: currentColor;
    animation: thinking-bounce 1.4s infinite ease-in-out both;
}

.thinking-dot:nth-child(1) {
    animation-delay: -0.32s;
}

.thinking-dot:nth-child(2) {
    animation-delay: -0.16s;
}

.thinking-dot:nth-child(3) {
    animation-delay: 0s;
}

@media (prefers-reduced-motion: reduce) {
    .thinking-dot {
        animation: none;
        opacity: 0.6;
        transform: none;
    }
}
</style>
