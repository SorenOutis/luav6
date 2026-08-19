import gsap from 'gsap';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { isLowEndDeviceSignal } from '@/lib/device';
import type {
    Appearance,
    CardStylePreset,
    FontPreset,
    ResolvedAppearance,
    ThemePreset,
} from '@/types';

export type {
    Appearance,
    CardStylePreset,
    FontPreset,
    ResolvedAppearance,
    ThemePreset,
};

export type ThemePresetOption = {
    id: ThemePreset;
    name: string;
    description: string;
    appearance: Appearance;
    swatches: string[];
};

export type FontPresetOption = {
    id: FontPreset;
    name: string;
    sample: string;
};

export type CardStylePresetOption = {
    id: CardStylePreset;
    name: string;
    description: string;
};

export const themePresets: ThemePresetOption[] = [
    {
        id: 'default',
        name: 'Default',
        description: 'Warm cream light mode, pure black dark mode.',
        appearance: 'system',
        swatches: ['#f5f0e8', '#000000', '#1a1a1e'],
    },
    {
        id: 'forest',
        name: 'Forest',
        description: 'Calm green learning space.',
        appearance: 'light',
        swatches: ['#f5f7f0', '#2f7d51', '#dcebd4'],
    },
];

export const fontPresets: FontPresetOption[] = [
    { id: 'system', name: 'System', sample: 'Aa' },
    { id: 'academic', name: 'Academic Serif', sample: 'Aa' },
    { id: 'rounded', name: 'Rounded', sample: 'Aa' },
    { id: 'mono', name: 'Mono', sample: 'Aa' },
];

export const cardStylePresets: CardStylePresetOption[] = [
    {
        id: 'current',
        name: 'Current',
        description: 'Clean, solid cards with no extra effects.',
    },
    {
        id: 'vibrant',
        name: 'Vibrant',
        description: 'Colorful spotlight glow and backdrop effects on cards.',
    },
    { id: 'soft', name: 'Soft', description: 'Rounder, calmer panels.' },
    {
        id: 'glass',
        name: 'Glass',
        description: 'Translucent dashboard surfaces.',
    },
    { id: 'sharp', name: 'Sharp', description: 'Square tactical panels.' },
];

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    themePreset: Ref<ThemePreset>;
    fontPreset: Ref<FontPreset>;
    cardStylePreset: Ref<CardStylePreset>;
    updateThemePreset: (value: ThemePreset) => void;
    updateFontPreset: (value: FontPreset) => void;
    updateCardStylePreset: (value: CardStylePreset) => void;
    themePresets: ThemePresetOption[];
    fontPresets: FontPresetOption[];
    cardStylePresets: CardStylePresetOption[];
    isTransitioningTheme: Ref<boolean>;
    toggleTheme: (event: MouseEvent) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const applyAppearanceAttributes = (
    themePreset: ThemePreset,
    fontPreset: FontPreset,
    cardStylePreset: CardStylePreset,
) => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.themePreset = themePreset;
    document.documentElement.dataset.fontPreset = fontPreset;
    document.documentElement.dataset.cardStyle = cardStylePreset;
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const getStoredThemePreset = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('themePreset') as ThemePreset | null;
};

const DEFAULT_THEME: ThemePreset = 'default';

const getStoredFontPreset = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('fontPreset') as FontPreset | null;
};

const getStoredCardStylePreset = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('cardStylePreset') as CardStylePreset | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    const savedThemePreset = getStoredThemePreset() || DEFAULT_THEME;
    const savedFontPreset = getStoredFontPreset() || 'system';
    const savedCardStylePreset = getStoredCardStylePreset() || 'current';

    updateTheme(savedAppearance || 'system');
    applyAppearanceAttributes(
        savedThemePreset,
        savedFontPreset,
        savedCardStylePreset,
    );

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');
const themePreset = ref<ThemePreset>(DEFAULT_THEME);
const fontPreset = ref<FontPreset>('system');
const cardStylePreset = ref<CardStylePreset>('current');
const isTransitioningTheme = ref(false);

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        themePreset.value = getStoredThemePreset() || DEFAULT_THEME;
        fontPreset.value = getStoredFontPreset() || 'system';
        cardStylePreset.value = getStoredCardStylePreset() || 'current';
        applyAppearanceAttributes(
            themePreset.value,
            fontPreset.value,
            cardStylePreset.value,
        );
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    function updateThemePreset(value: ThemePreset) {
        const preset = themePresets.find((theme) => theme.id === value);

        themePreset.value = value;
        localStorage.setItem('themePreset', value);
        setCookie('themePreset', value);
        applyAppearanceAttributes(
            themePreset.value,
            fontPreset.value,
            cardStylePreset.value,
        );

        if (preset) {
            updateAppearance(preset.appearance);
        }
    }

    function updateFontPreset(value: FontPreset) {
        fontPreset.value = value;
        localStorage.setItem('fontPreset', value);
        setCookie('fontPreset', value);
        applyAppearanceAttributes(
            themePreset.value,
            fontPreset.value,
            cardStylePreset.value,
        );
    }

    function updateCardStylePreset(value: CardStylePreset) {
        cardStylePreset.value = value;
        localStorage.setItem('cardStylePreset', value);
        setCookie('cardStylePreset', value);
        applyAppearanceAttributes(
            themePreset.value,
            fontPreset.value,
            cardStylePreset.value,
        );
    }

    function toggleTheme(event: MouseEvent) {
        const newTheme = appearance.value === 'dark' ? 'light' : 'dark';

        // Guard against a second tap while a reveal is still running —
        // startViewTransition() is single-flight and would throw, leaving the
        // theme stuck mid-transition. Apply the new theme instantly instead.
        if (isTransitioningTheme.value) {
            updateAppearance(newTheme);
            return;
        }

        const supportsViewTransition =
            typeof (document as any).startViewTransition === 'function';
        const prefersReducedMotion =
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // The full-page View Transition snapshot + per-frame clip-path masking
        // is the whole reason the circular reveal stutters on phones. Touch
        // devices are already flagged as low-end (see @/lib/device and the
        // `data-low-end` attribute) and have every other heavy effect disabled
        // there — so skip the ripple and use a cheap, compositor-only fade so
        // the switch still reads as animated without the jank.
        if (
            !supportsViewTransition ||
            prefersReducedMotion ||
            isLowEndDeviceSignal()
        ) {
            if (prefersReducedMotion) {
                updateAppearance(newTheme);
                return;
            }

            document.documentElement.classList.remove('theme-fade');
            // Force a reflow so the fade restarts on rapid consecutive taps.
            void document.documentElement.offsetWidth;
            document.documentElement.classList.add('theme-fade');
            updateAppearance(newTheme);
            return;
        }

        const x = event.clientX;
        const y = event.clientY;
        const endRadius = Math.hypot(
            Math.max(x, innerWidth - x),
            Math.max(y, innerHeight - y),
        );

        isTransitioningTheme.value = true;
        document.documentElement.classList.add('theme-transitioning');
        gsap.globalTimeline.pause();

        const transition = (document as any).startViewTransition(() => {
            updateAppearance(newTheme);
        });

        transition.ready.then(() => {
            const clipPath = [
                `circle(0px at ${x}px ${y}px)`,
                `circle(${endRadius}px at ${x}px ${y}px)`,
            ];

            const animation = document.documentElement.animate(
                {
                    clipPath:
                        newTheme === 'dark'
                            ? [...clipPath].reverse()
                            : clipPath,
                },
                {
                    duration: 400,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    pseudoElement:
                        newTheme === 'dark'
                            ? '::view-transition-old(root)'
                            : '::view-transition-new(root)',
                },
            );

            animation.onfinish = () => {
                isTransitioningTheme.value = false;
                document.documentElement.classList.remove(
                    'theme-transitioning',
                );
                gsap.globalTimeline.resume();
            };
        });

        transition.finished.finally(() => {
            isTransitioningTheme.value = false;
            document.documentElement.classList.remove('theme-transitioning');
            gsap.globalTimeline.resume();
        });
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        themePreset,
        fontPreset,
        cardStylePreset,
        updateThemePreset,
        updateFontPreset,
        updateCardStylePreset,
        themePresets,
        fontPresets,
        cardStylePresets,
        isTransitioningTheme,
        toggleTheme,
    };
}
