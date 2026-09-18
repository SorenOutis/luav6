import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import { useMobile } from '@/composables/useMobile';

const originalMatchMedia = window.matchMedia;
const originalMaxTouchPoints = navigator.maxTouchPoints;
const originalHardwareConcurrency = navigator.hardwareConcurrency;

function stubMatchMedia(impl: (query: string) => boolean): void {
    window.matchMedia = ((query: string) => ({
        matches: impl(query),
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    })) as typeof window.matchMedia;
}

function stubNavigator(opts: {
    maxTouchPoints?: number;
    hardwareConcurrency?: number;
}): void {
    Object.defineProperty(navigator, 'maxTouchPoints', {
        configurable: true,
        value: opts.maxTouchPoints ?? 0,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        configurable: true,
        value: opts.hardwareConcurrency ?? 8,
    });
}

const Probe = defineComponent({
    setup() {
        return useMobile();
    },
    template:
        '<div>{{ isLowEndDevice }}|{{ isCoarsePointer }}|{{ prefersReducedMotion }}</div>',
});

afterEach(() => {
    window.matchMedia = originalMatchMedia;
    Object.defineProperty(navigator, 'maxTouchPoints', {
        configurable: true,
        value: originalMaxTouchPoints,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        configurable: true,
        value: originalHardwareConcurrency,
    });
    document.documentElement.removeAttribute('data-low-end');
});

describe('useMobile', () => {
    it('detects a phone on the first render, before onMounted', () => {
        stubNavigator({ maxTouchPoints: 5, hardwareConcurrency: 8 });
        stubMatchMedia((query) => query.includes('pointer: coarse'));

        const wrapper = mount(Probe);

        // The welcome page used to boot GSAP / particles because these
        // refs stayed false until onMounted. First paint must already
        // be the lite path.
        expect(wrapper.text()).toBe('true|true|false');
        wrapper.unmount();
    });

    it('detects reduced-motion on the first render', () => {
        stubNavigator({ maxTouchPoints: 0, hardwareConcurrency: 8 });
        stubMatchMedia((query) =>
            query.includes('prefers-reduced-motion: reduce'),
        );
        delete (window as Window & { ontouchstart?: unknown }).ontouchstart;

        const wrapper = mount(Probe);

        expect(wrapper.text()).toBe('true|false|true');
        wrapper.unmount();
    });
});
