import { onBeforeUnmount, onMounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';

/**
 * Drives a scripted, looping demo by advancing a `phase` index through
 * a list of per-phase durations. Used by the welcome page demo windows
 * (hero scripted loop, feature card vignettes).
 *
 * The loop only runs while `playing` is true — callers stop it when the
 * element leaves the viewport (with `useInView`) or when reduced motion
 * is requested, then park it on a static phase.
 *
 * `durations` may be reactive (a ref/getter) when phases are timed to
 * per-scenario content; durations are resolved on every schedule.
 */
export function useDemoLoop(durations: MaybeRefOrGetter<number[]>) {
    const phase = ref(0);
    /** Increments each time the loop wraps back to phase 0. */
    const cycle = ref(0);
    const playing = ref(false);
    let timer: number | undefined;

    const steps = () => toValue(durations);

    const clear = () => {
        if (timer !== undefined && typeof window !== 'undefined') {
            window.clearTimeout(timer);
        }
        timer = undefined;
    };

    const schedule = () => {
        clear();

        const durationsNow = steps();

        if (
            !playing.value ||
            durationsNow.length === 0 ||
            typeof window === 'undefined'
        ) {
            return;
        }

        timer = window.setTimeout(() => {
            if (!playing.value) {
                return;
            }

            const next = (phase.value + 1) % durationsNow.length;

            if (next === 0 && durationsNow.length > 1) {
                cycle.value += 1;
            }

            phase.value = next;
            schedule();
        }, durationsNow[phase.value]);
    };

    const start = () => {
        playing.value = true;
    };

    const stop = () => {
        playing.value = false;
    };

    /**
     * Park the loop on a specific phase. A running loop is rescheduled so the
     * new phase gets its own full budget instead of inheriting the leftover
     * of the phase it replaced.
     */
    const set = (next: number) => {
        phase.value = Math.min(Math.max(next, 0), steps().length - 1);

        if (playing.value) {
            schedule();
        }
    };

    watch(playing, (value) => (value ? schedule() : clear()));
    onBeforeUnmount(clear);

    return { phase, cycle, playing, start, stop, set, reset: () => set(0) };
}

/**
 * Tracks whether `target` is currently intersecting the viewport.
 * Defaults to `true` so SSR and environments without
 * IntersectionObserver render the active state.
 */
export function useInView(target: Ref<HTMLElement | null>) {
    const inView = ref(true);
    let observer: IntersectionObserver | undefined;

    onMounted(() => {
        const el = target.value;

        if (!el || typeof IntersectionObserver === 'undefined') {
            return;
        }

        observer = new IntersectionObserver(
            ([entry]) => {
                inView.value = entry.isIntersecting;
            },
            { rootMargin: '0px 0px -12% 0px' },
        );
        observer.observe(el);
    });

    onBeforeUnmount(() => observer?.disconnect());

    return inView;
}
