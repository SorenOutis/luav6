<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * A friendly, branded empty state featuring the fox mascot.
 *
 * Drop it in wherever a section has nothing to show yet — no assignments,
 * no events, an empty library, etc. The illustrations live in
 * `/images/mascots/` (transparent WebP + PNG fallback) so they blend into
 * both light and dark themes.
 *
 * Usage:
 *   <MascotEmptyState
 *       mascot="assignments"
 *       title="No assignments yet"
 *       description="New work will show up here once your teacher assigns it."
 *   >
 *       <Button>Browse the library</Button>
 *   </MascotEmptyState>
 */
const props = withDefaults(
    defineProps<{
        /** Mascot illustration key, e.g. "welcome", "assignments", "library". */
        mascot?: string;
        /** Bold heading shown under the illustration. */
        title: string;
        /** Supporting sentence under the title. */
        description?: string;
        /** Illustration width in pixels. */
        size?: number;
        /** Render without extra vertical padding (inline on an existing surface). */
        bare?: boolean;
    }>(),
    {
        mascot: 'welcome',
        description: '',
        size: 160,
        bare: false,
    },
);

// Prefer WebP (much smaller); if it ever fails to load, fall back to the PNG.
// Using a Vue @error handler keeps this CSP-safe (no inline onerror string).
const webpFailed = ref(false);
const src = computed(() =>
    webpFailed.value
        ? `/images/mascots/fox-${props.mascot}.png`
        : `/images/mascots/fox-${props.mascot}.webp`,
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 text-center"
        :class="bare ? 'py-10' : 'py-14'"
        role="status"
    >
        <img
            :src="src"
            :alt="title"
            class="motion-safe:animate-mascot-float h-auto w-auto object-contain select-none"
            :style="{ width: `${size}px`, maxWidth: '80vw' }"
            draggable="false"
            loading="lazy"
            decoding="async"
            @error="webpFailed = true"
        />

        <h3 class="mt-5 text-[17px] font-semibold tracking-tight">
            {{ title }}
        </h3>

        <p
            v-if="description"
            class="mt-2 max-w-md text-sm leading-6 text-muted-foreground"
        >
            {{ description }}
        </p>

        <div
            v-if="$slots.default"
            class="mt-5 flex flex-wrap items-center justify-center gap-3"
        >
            <slot />
        </div>
    </div>
</template>

<style scoped>
@keyframes mascot-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

.animate-mascot-float {
    animation: mascot-float 3.5s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .animate-mascot-float {
        animation: none;
    }
}
</style>
