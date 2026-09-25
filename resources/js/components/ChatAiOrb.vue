<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg' | 'status';
        state?: 'idle' | 'listening' | 'thinking' | 'speaking' | 'asleep';
        animateIdle?: boolean;
        color?: string;
    }>(),
    { size: 'md', state: 'idle', animateIdle: false },
);

const root = ref<HTMLElement>();
const paused = ref(false);
const reducedMotion = ref(false);
let visible = true;
let motionQuery: MediaQueryList | undefined;
let observer: IntersectionObserver | undefined;
const motion = computed(() =>
    props.animateIdle && props.state === 'idle' ? 'welcome' : props.state,
);

function syncPlayback() {
    reducedMotion.value = motionQuery?.matches ?? false;
    paused.value = document.hidden || !visible;
}

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    motionQuery.addEventListener('change', syncPlayback);
    document.addEventListener('visibilitychange', syncPlayback);
    if (root.value && typeof IntersectionObserver !== 'undefined') {
        observer = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            syncPlayback();
        });
        observer.observe(root.value);
    }
    syncPlayback();
});

onBeforeUnmount(() => {
    motionQuery?.removeEventListener('change', syncPlayback);
    document.removeEventListener('visibilitychange', syncPlayback);
    observer?.disconnect();
});
</script>

<template>
    <div
        ref="root"
        class="wolf-persona relative shrink-0 text-foreground select-none"
        :class="{
            'h-24 w-24 sm:h-28 sm:w-28': size === 'md',
            'h-20 w-20': size === 'sm',
            'h-32 w-32': size === 'lg',
            'h-7 w-7': size === 'status',
            'is-paused': paused,
            'is-reduced': reducedMotion,
        }"
        :data-motion="motion"
        :style="color ? { color } : undefined"
        aria-hidden="true"
    >
        <svg
            class="h-full w-full"
            viewBox="0 0 120 120"
            fill="none"
            focusable="false"
            data-wolf-mark
        >
            <circle
                class="wolf-circle"
                cx="60"
                cy="60"
                r="30"
                fill="currentColor"
            />
            <!-- Geometric 4-point star/spark of insight -->
            <g class="wolf-spark">
                <path
                    d="M60 16 L68 52 L104 60 L68 68 L60 104 L52 68 L16 60 L52 52Z"
                    fill="currentColor"
                />
                <path
                    d="M60 36 L64 56 L84 60 L64 64 L60 84 L56 64 L36 60 L56 56Z"
                    fill="var(--color-card)"
                    opacity=".35"
                />
            </g>
            <g class="wolf-head">
                <!-- Separate facets leave negative-space eyes and a long canine muzzle. -->
                <g class="wolf-ear wolf-ear-left">
                    <path d="M22 52 18 14 45 35 37 50Z" fill="currentColor" />
                    <path
                        d="M24 26 36 37 27 44Z"
                        fill="var(--color-card)"
                        opacity=".65"
                    />
                </g>
                <g class="wolf-ear wolf-ear-right">
                    <path d="M98 52 102 14 75 35 83 50Z" fill="currentColor" />
                    <path
                        d="M96 26 84 37 93 44Z"
                        fill="var(--color-card)"
                        opacity=".65"
                    />
                </g>
                <path
                    d="M24 51 46 33 60 40 53 64 39 55 34 66 18 59Z"
                    fill="currentColor"
                />
                <path
                    d="M96 51 74 33 60 40 67 64 81 55 86 66 102 59Z"
                    fill="currentColor"
                />
                <path
                    d="M46 35 60 40 74 35 65 69 60 79 55 69Z"
                    fill="currentColor"
                    opacity=".65"
                />
                <path
                    d="M18 64 33 69 42 65 51 77 52 91 32 81Z M102 64 87 69 78 65 69 77 68 91 88 81Z"
                    fill="currentColor"
                    opacity=".8"
                />
                <g class="wolf-muzzle">
                    <path
                        d="M44 68 57 80 63 80 76 68 65 95 60 103 55 95Z"
                        fill="currentColor"
                    />
                    <path d="M53 80 67 80 60 87Z" fill="var(--color-card)" />
                </g>
                <path
                    d="M39 58 49 63 41 63Z M81 58 71 63 79 63Z"
                    fill="var(--color-card)"
                />
            </g>
        </svg>
    </div>
</template>

<style scoped>
.wolf-head,
.wolf-circle,
.wolf-spark {
    transform-box: view-box;
    transform-origin: 60px 60px;
}
.wolf-ear-left {
    transform-origin: 35px 48px;
}
.wolf-ear-right {
    transform-origin: 85px 48px;
}
.wolf-muzzle {
    transform-origin: 60px 78px;
}

.wolf-circle,
.wolf-spark {
    opacity: 0;
}

/* ── Tri-form cycle: Fox -> Circle -> Spark -> Fox (3.6s) ── */
[data-motion='welcome'] .wolf-head {
    animation: wolf-to-circle 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='welcome'] .wolf-circle {
    animation: wolf-circle-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='welcome'] .wolf-spark {
    animation: wolf-spark-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='welcome'] .wolf-ear-left {
    animation: wolf-ear-fold-left 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='welcome'] .wolf-ear-right {
    animation: wolf-ear-fold-right 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='welcome'] .wolf-muzzle {
    animation: wolf-muzzle-retract 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}

[data-motion='listening'] .wolf-ear-left {
    animation: wolf-listen-left 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='listening'] .wolf-ear-right {
    animation: wolf-listen-right 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='thinking'] .wolf-head {
    animation: wolf-think 2s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}
[data-motion='speaking'] .wolf-muzzle {
    animation: wolf-speak 0.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
}

[data-motion='asleep'] .wolf-head {
    opacity: 0.55;
}

/* ── Reduced motion: gentle tri-form crossfade ── */
.is-reduced [data-motion='welcome'] .wolf-head {
    animation: wolf-fade-fox 3.6s ease-in-out infinite !important;
}
.is-reduced [data-motion='welcome'] .wolf-circle {
    animation: wolf-fade-circle 3.6s ease-in-out infinite !important;
}
.is-reduced [data-motion='welcome'] .wolf-spark {
    animation: wolf-fade-spark 3.6s ease-in-out infinite !important;
}
.is-reduced [data-motion='welcome'] .wolf-ear-left,
.is-reduced [data-motion='welcome'] .wolf-ear-right,
.is-reduced [data-motion='welcome'] .wolf-muzzle {
    animation: none !important;
}

/* ── Paused when offscreen or in background tab ── */
.is-paused .wolf-circle,
.is-paused .wolf-spark,
.is-paused .wolf-head,
.is-paused .wolf-ear,
.is-paused .wolf-muzzle {
    animation-play-state: paused !important;
}

/* ── Keyframes: 3.6s Tri-Form Morph ──
   0% – 14%:  Fox rests
   14% – 26%: Fox -> Circle morph
   26% – 42%: Circle holds
   42% – 54%: Circle -> Spark morph
   54% – 72%: Spark holds
   72% – 86%: Spark -> Fox morph
   86% – 100%: Fox rests complete
*/
@keyframes wolf-to-circle {
    0%,
    14%,
    86%,
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
    26%,
    74% {
        transform: scale(0.2) rotate(30deg);
        opacity: 0;
    }
}

@keyframes wolf-circle-reveal {
    0%,
    14%,
    54%,
    100% {
        transform: scale(0);
        opacity: 0;
    }
    26%,
    42% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes wolf-spark-reveal {
    0%,
    42%,
    86%,
    100% {
        transform: scale(0) rotate(-35deg);
        opacity: 0;
    }
    54%,
    72% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

@keyframes wolf-ear-fold-left {
    0%,
    14%,
    86%,
    100% {
        transform: rotate(0deg);
    }
    26%,
    74% {
        transform: rotate(25deg) translate(6px, 8px);
    }
}

@keyframes wolf-ear-fold-right {
    0%,
    14%,
    86%,
    100% {
        transform: rotate(0deg);
    }
    26%,
    74% {
        transform: rotate(-25deg) translate(-6px, 8px);
    }
}

@keyframes wolf-muzzle-retract {
    0%,
    14%,
    86%,
    100% {
        transform: translateY(0) scale(1);
    }
    26%,
    74% {
        transform: translateY(-8px) scale(0.7);
    }
}

@keyframes wolf-fade-fox {
    0%,
    14%,
    86%,
    100% {
        opacity: 1;
    }
    26%,
    74% {
        opacity: 0;
    }
}

@keyframes wolf-fade-circle {
    0%,
    14%,
    54%,
    100% {
        opacity: 0;
    }
    26%,
    42% {
        opacity: 1;
    }
}

@keyframes wolf-fade-spark {
    0%,
    42%,
    86%,
    100% {
        opacity: 0;
    }
    54%,
    72% {
        opacity: 1;
    }
}

@keyframes wolf-listen-left {
    0%,
    80%,
    100% {
        transform: rotate(0deg);
    }
    40% {
        transform: rotate(-7deg);
    }
}
@keyframes wolf-listen-right {
    0%,
    80%,
    100% {
        transform: rotate(0deg);
    }
    40% {
        transform: rotate(7deg);
    }
}
@keyframes wolf-think {
    0%,
    100% {
        transform: rotate(-3deg);
        opacity: 1;
    }
    50% {
        transform: rotate(3deg);
        opacity: 0.7;
    }
}
@keyframes wolf-speak {
    0%,
    100% {
        transform: scaleY(1);
    }
    50% {
        transform: scaleY(0.92);
    }
}
</style>
