import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

const root = process.cwd();
const read = (rel: string) => readFileSync(join(root, rel), 'utf8');

const LinkStub = defineComponent({
    props: { href: { type: String, default: '#' } },
    setup(props, { slots }) {
        return () => h('a', { href: props.href }, slots.default?.());
    },
});

const pinDesktopHardware = () => {
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        value: 8,
        configurable: true,
    });
    Object.defineProperty(navigator, 'deviceMemory', {
        value: 8,
        configurable: true,
    });

    // jsdom reports a touch-capable window; strip the signal so the
    // low-end heuristic sees a plain desktop device.
    for (const target of [window, Window.prototype]) {
        try {
            delete (target as unknown as Record<string, unknown>).ontouchstart;
        } catch {
            // Non-configurable in some environments — leave it.
        }
    }
};

describe('welcome scroll reveals', () => {
    it('staggered rv content renders inside reveal groups', () => {
        const wrapper = mount(FeatureCards, {
            global: { stubs: { Link: LinkStub } },
        });

        const groups = wrapper.findAll('.rv-group');
        expect(groups.length).toBeGreaterThanOrEqual(2);

        const items = wrapper.findAll('.rv');
        expect(items).toHaveLength(7);

        items.forEach((item, index) => {
            expect(item.attributes('style')).toContain(
                index < 3 ? `--rv-i: ${index}` : `--rv-i: ${index - 3}`,
            );
        });

        // Unarmed in jsdom (no IntersectionObserver): content stays visible.
        expect(wrapper.find('.rv-group.is-revealing').exists()).toBe(false);

        wrapper.unmount();
    });

    it('arms the reveal when the group scrolls into view', async () => {
        pinDesktopHardware();

        let trigger:
            | ((entries: Array<Partial<IntersectionObserverEntry>>) => void)
            | undefined;

        class MockIntersectionObserver {
            constructor(
                callback: (
                    entries: Array<Partial<IntersectionObserverEntry>>,
                ) => void,
            ) {
                trigger = callback;
            }

            observe() {}

            unobserve() {}

            disconnect() {}
        }

        vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);

        const wrapper = mount(RevealGroup, {
            slots: { default: '<p class="rv">Hello</p>' },
        });

        expect(wrapper.classes()).toContain('rv-group');
        expect(wrapper.classes()).not.toContain('is-revealing');

        trigger?.([{ isIntersecting: true }]);
        await nextTick();

        expect(wrapper.classes()).toContain('is-revealing');

        wrapper.unmount();
        vi.unstubAllGlobals();
    });

    it('ships reveal CSS with reduced-motion and low-end guards', () => {
        const css = read('resources/css/app.css');

        expect(css).toContain('.rv-group.is-revealing .rv');
        expect(css).toContain('@keyframes rv-in');
        expect(css).toContain('.rv-group.is-revealing .rv-pop');
        expect(css).toContain('html[data-low-end] .rv-group.is-revealing .rv');
        expect(css).toContain('var(--rv-stagger, 80ms)');
    });

    it('keeps the welcome page on reveal groups instead of motion', () => {
        const page = read('resources/js/pages/Welcome.vue');

        expect(page).toContain('<RevealGroup');
        expect(page).toContain('id="how-it-works"');
        expect(page).toContain('id="contact"');
        expect(page).not.toContain('@motionone/vue');
    });
});
