import { readFileSync } from 'node:fs';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

let reduced = false;
let hidden = false;
let motionChanged: () => void;
let intersect: (entries: { isIntersecting: boolean }[]) => void;
const removeMotionListener = vi.fn();
const observe = vi.fn();
const disconnect = vi.fn();
const wrappers: ReturnType<typeof mount>[] = [];

function render(props = {}) {
    const wrapper = mount(ChatAiOrb, { props });
    wrappers.push(wrapper);
    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    reduced = false;
    hidden = false;
    vi.spyOn(document, 'hidden', 'get').mockImplementation(() => hidden);
    vi.stubGlobal('matchMedia', () => ({
        get matches() {
            return reduced;
        },
        addEventListener: (_: string, callback: () => void) => {
            motionChanged = callback;
        },
        removeEventListener: removeMotionListener,
    }));
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: typeof intersect) {
                intersect = callback;
            }
            observe = observe;
            disconnect = disconnect;
        },
    );
});

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('ChatAiOrb geometric wolf', () => {
    it('renders local decorative SVG with grouped animated facets', () => {
        const wrapper = render();
        expect(wrapper.find('svg[data-wolf-mark]').attributes('viewBox')).toBe(
            '0 0 120 120',
        );
        expect(wrapper.attributes('aria-hidden')).toBe('true');
        expect(wrapper.find('svg').attributes('focusable')).toBe('false');
        expect(wrapper.find('canvas, image, img').exists()).toBe(false);
        for (const part of [
            'wolf-ear-left',
            'wolf-ear-right',
            'wolf-muzzle',
            'wolf-spark',
        ]) {
            expect(wrapper.findAll(`g.${part} path`)).toHaveLength(2);
        }
    });

    it('keeps ordinary idle static and maps each explicit state', async () => {
        const wrapper = render();
        expect(wrapper.attributes('data-motion')).toBe('idle');
        for (const state of [
            'listening',
            'thinking',
            'speaking',
            'asleep',
            'idle',
        ] as const) {
            await wrapper.setProps({ state });
            expect(wrapper.attributes('data-motion')).toBe(state);
        }
    });

    it('uses welcome motion only for opted-in idle state', async () => {
        const wrapper = render({ animateIdle: true });
        expect(wrapper.attributes('data-motion')).toBe('welcome');
        await wrapper.setProps({ state: 'thinking' });
        expect(wrapper.attributes('data-motion')).toBe('thinking');
        await wrapper.setProps({ state: 'idle' });
        expect(wrapper.attributes('data-motion')).toBe('welcome');
        await wrapper.setProps({ animateIdle: false });
        expect(wrapper.attributes('data-motion')).toBe('idle');
    });

    it('inherits theme color and supports reactive brand overrides', async () => {
        const wrapper = render();
        expect(wrapper.classes()).toContain('text-foreground');
        expect(wrapper.element.style.color).toBe('');
        await wrapper.setProps({ color: '#f59e0b' });
        expect(wrapper.element.style.color).toBe('rgb(245, 158, 11)');
        await wrapper.setProps({ color: '#ea580c' });
        expect(wrapper.element.style.color).toBe('rgb(234, 88, 12)');
        await wrapper.setProps({ color: undefined });
        expect(wrapper.element.style.color).toBe('');
    });

    it('applies reduced motion variant on preference changes without hiding wolf', async () => {
        reduced = true;
        const wrapper = render({ animateIdle: true });
        await nextTick();
        expect(wrapper.classes()).toContain('is-reduced');
        expect(wrapper.find('[data-wolf-mark]').exists()).toBe(true);
        reduced = false;
        motionChanged();
        await nextTick();
        expect(wrapper.classes()).not.toContain('is-reduced');
        reduced = true;
        motionChanged();
        await nextTick();
        expect(wrapper.classes()).toContain('is-reduced');
    });

    it('pauses offscreen and resumes only when visible in active document', async () => {
        const wrapper = render({ state: 'thinking' });
        expect(observe).toHaveBeenCalledWith(wrapper.element);
        intersect([{ isIntersecting: false }]);
        await nextTick();
        expect(wrapper.classes()).toContain('is-paused');
        hidden = true;
        document.dispatchEvent(new Event('visibilitychange'));
        intersect([{ isIntersecting: true }]);
        await nextTick();
        expect(wrapper.classes()).toContain('is-paused');
        hidden = false;
        document.dispatchEvent(new Event('visibilitychange'));
        await nextTick();
        expect(wrapper.classes()).not.toContain('is-paused');
    });

    it('pauses when mounted in hidden document', async () => {
        hidden = true;
        const wrapper = render({ animateIdle: true });
        await nextTick();
        expect(wrapper.classes()).toContain('is-paused');
    });

    it('supports browsers without IntersectionObserver', () => {
        vi.stubGlobal('IntersectionObserver', undefined);
        expect(render().find('[data-wolf-mark]').exists()).toBe(true);
    });

    it('removes listeners and disconnects observer on unmount', () => {
        const removeDocumentListener = vi.spyOn(
            document,
            'removeEventListener',
        );
        render();
        wrappers.pop()!.unmount();
        expect(removeMotionListener).toHaveBeenCalledWith(
            'change',
            motionChanged,
        );
        expect(removeDocumentListener).toHaveBeenCalledWith(
            'visibilitychange',
            motionChanged,
        );
        expect(disconnect).toHaveBeenCalledOnce();
    });

    it('preserves welcome and compact status sizes', () => {
        expect(render().classes()).toContain('h-24');
        expect(render({ size: 'sm' }).classes()).toContain('h-20');
        expect(render({ size: 'lg' }).classes()).toContain('h-32');
        expect(render({ size: 'status' }).classes()).toContain('h-7');
    });

    it('gates CSS motion and preserves welcome rest', () => {
        const source = readFileSync(
            'resources/js/components/ChatAiOrb.vue',
            'utf8',
        );
        expect(source).toContain('wolf-to-circle 3.6s');
        expect(source).toContain('wolf-circle-reveal 3.6s');
        expect(source).toContain('wolf-spark-reveal 3.6s');
        expect(source).toContain('wolf-fade-fox 3.6s');
        expect(source).toContain('wolf-fade-spark 3.6s');
        expect(source).toContain(".is-reduced [data-motion='welcome']");
        expect(source).toContain('.is-paused .wolf-spark');
        expect(render().find('circle.wolf-circle').attributes('r')).toBe('30');
        expect(render().find('g.wolf-spark').exists()).toBe(true);
        expect(source).not.toMatch(
            /@rive-app|radial-gradient|setInterval|setTimeout/,
        );
    });
});
