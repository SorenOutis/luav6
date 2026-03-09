import { ref, watch, onMounted } from 'vue';
import gsap from 'gsap';

/**
 * useNumberAnimation - Animates a numeric value from its previous state to a new state using GSAP.
 * 
 * @param getter - A function that returns the current target value.
 * @param duration - Animation duration in seconds (default: 1).
 * @param ease - GSAP ease string (default: 'expo.out').
 */
export function useNumberAnimation(getter: () => number, duration = 1, ease = 'expo.out') {
    const displayValue = ref(getter());

    watch(getter, (newValue) => {
        gsap.to(displayValue, {
            value: newValue,
            duration,
            ease,
            onUpdate: () => {
                // Ensure we don't end up with long floating point numbers
                displayValue.value = Math.floor(displayValue.value);
            }
        });
    }, { immediate: true });

    return displayValue;
}
