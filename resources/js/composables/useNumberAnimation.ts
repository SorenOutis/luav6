import { ref, watch } from 'vue';
import gsap from 'gsap';
import { useAppearance } from '@/composables/useAppearance';

/**
 * useNumberAnimation - Animates a numeric value from its previous state to a new state using GSAP.
 * 
 * @param getter - A function that returns the current target value.
 * @param duration - Animation duration in seconds (default: 1).
 * @param ease - GSAP ease string (default: 'expo.out').
 */
export function useNumberAnimation(getter: () => number, duration = 1, ease = 'expo.out') {
    const { isTransitioningTheme } = useAppearance();
    const displayValue = ref(getter());
    let animation: gsap.core.Tween | null = null;

    watch(getter, (newValue) => {
        if (animation) animation.kill();

        animation = gsap.to(displayValue, {
            value: newValue,
            duration: isTransitioningTheme.value ? 0 : duration,
            ease,
            onUpdate: () => {
                // Ensure we don't end up with long floating point numbers
                displayValue.value = Math.floor(displayValue.value);
            }
        });
    }, { immediate: true });

    // When theme transition starts, we should complete current animation immediately
    watch(isTransitioningTheme, (isTransitioning) => {
        if (isTransitioning && animation && animation.isActive()) {
            animation.progress(1);
        }
    });

    return displayValue;
}
