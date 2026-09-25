import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

const mocks = vi.hoisted(() => ({
    options: {} as Record<string, any>,
    inputs: ['listening', 'thinking', 'speaking', 'asleep'].map((name) => ({
        name,
        value: false,
    })),
    cleanup: vi.fn(),
    stopRendering: vi.fn(),
    startRendering: vi.fn(),
    rgb: vi.fn(),
    resize: vi.fn(),
    setWasmUrl: vi.fn(),
    setWasmFallbackUrl: vi.fn(),
}));

vi.mock('@rive-app/webgl2', () => ({
    RuntimeLoader: {
        setWasmUrl: mocks.setWasmUrl,
        setWasmFallbackUrl: mocks.setWasmFallbackUrl,
    },
    Rive: class {
        constructor(options: Record<string, any>) {
            mocks.options = options;
        }
        stateMachineInputs() {
            return mocks.inputs;
        }
        cleanup = mocks.cleanup;
        stopRendering = mocks.stopRendering;
        startRendering = mocks.startRendering;
        resizeDrawingSurfaceToCanvas = mocks.resize;
        viewModelInstance = { color: () => ({ rgb: mocks.rgb }) };
    },
}));

let reduced = false;
let motionChanged: () => void;
let intersect: (entries: { isIntersecting: boolean }[]) => void;
const wrappers: ReturnType<typeof mount>[] = [];

function render(props = {}) {
    const wrapper = mount(ChatAiOrb, { props });
    wrappers.push(wrapper);
    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    mocks.options = {};
    mocks.inputs.forEach((input) => {
        input.value = false;
    });
    reduced = false;
    vi.stubGlobal('matchMedia', () => ({
        get matches() {
            return reduced;
        },
        addEventListener: (_: string, callback: () => void) => {
            motionChanged = callback;
        },
        removeEventListener: vi.fn(),
    }));
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            disconnect() {}
        },
    );
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: typeof intersect) {
                intersect = callback;
            }
            observe() {}
            disconnect() {}
        },
    );
});

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    document.documentElement.classList.remove('dark');
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('ChatAiOrb Command persona', () => {
    it('loads Command with local WASM and applies latest state after load', async () => {
        const wrapper = render();
        await flushPromises();
        await wrapper.setProps({ state: 'thinking' });
        expect(mocks.options.src).toMatch(/command-2\.0\.riv$/);
        expect(mocks.options.stateMachines).toBe('default');
        expect(mocks.options.autoBind).toBe(true);
        expect(mocks.setWasmUrl).toHaveBeenCalled();
        expect(mocks.setWasmFallbackUrl).toHaveBeenCalledWith(null);
        mocks.options.onLoad();
        await flushPromises();
        expect(mocks.inputs.map((input) => input.value)).toEqual([
            false,
            true,
            false,
            false,
        ]);
        expect(wrapper.find('[data-persona-fallback]').exists()).toBe(false);
        await wrapper.setProps({ state: 'speaking' });
        expect(mocks.inputs.map((input) => input.value)).toEqual([
            false,
            false,
            true,
            false,
        ]);
        await wrapper.setProps({ state: 'idle' });
        expect(mocks.inputs.every((input) => !input.value)).toBe(true);
    });

    it('follows actual theme and pauses when offscreen', async () => {
        render();
        await flushPromises();
        mocks.options.onLoad();
        expect(mocks.rgb).toHaveBeenLastCalledWith(0, 0, 0);
        document.documentElement.classList.add('dark');
        await flushPromises();
        expect(mocks.rgb).toHaveBeenLastCalledWith(255, 255, 255);
        intersect([{ isIntersecting: false }]);
        expect(mocks.stopRendering).toHaveBeenCalled();
        intersect([{ isIntersecting: true }]);
        expect(mocks.startRendering).toHaveBeenCalled();
    });

    it('applies custom system accent color when provided and updates dynamically', async () => {
        const wrapper = render({ color: '#f59e0b' });
        await flushPromises();
        mocks.options.onLoad();
        expect(mocks.rgb).toHaveBeenLastCalledWith(245, 158, 11);
        await wrapper.setProps({ color: '#ea580c' });
        expect(mocks.rgb).toHaveBeenLastCalledWith(234, 88, 12);
    });

    it('uses static fallback for reduced motion and reacts to preference changes', async () => {
        reduced = true;
        const wrapper = render();
        await flushPromises();
        expect(mocks.options.src).toBeUndefined();
        expect(wrapper.find('[data-persona-fallback]').exists()).toBe(true);
        reduced = false;
        motionChanged();
        await flushPromises();
        mocks.options.onLoad();
        reduced = true;
        motionChanged();
        await flushPromises();
        expect(mocks.stopRendering).toHaveBeenCalled();
        expect(wrapper.find('[data-persona-fallback]').exists()).toBe(true);
    });

    it('cleans up failed loads and retains fallback', async () => {
        const wrapper = render();
        await flushPromises();
        mocks.options.onLoadError();
        await flushPromises();
        expect(mocks.cleanup).toHaveBeenCalledOnce();
        expect(wrapper.find('[data-persona-fallback]').exists()).toBe(true);
    });

    it('cleans up on unmount and ignores late load callbacks', async () => {
        const wrapper = render();
        await flushPromises();
        wrapper.unmount();
        mocks.options.onLoad();
        expect(mocks.cleanup).toHaveBeenCalledOnce();
        expect(mocks.resize).not.toHaveBeenCalled();
    });

    it('does not create runtime when unmounted during lazy import', async () => {
        const wrapper = render();
        wrapper.unmount();
        await flushPromises();
        expect(mocks.options.src).toBeUndefined();
    });

    it('cycles actual listening input for two seconds with half-second idle rest', async () => {
        const wrapper = render({ animateIdle: true });
        await flushPromises();
        vi.useFakeTimers();
        mocks.options.onLoad();
        expect(mocks.inputs[0].value).toBe(true);
        vi.advanceTimersByTime(1999);
        expect(mocks.inputs[0].value).toBe(true);
        vi.advanceTimersByTime(1);
        expect(mocks.inputs.every((input) => !input.value)).toBe(true);
        vi.advanceTimersByTime(499);
        expect(mocks.inputs[0].value).toBe(false);
        vi.advanceTimersByTime(1);
        expect(mocks.inputs[0].value).toBe(true);
        await wrapper.setProps({ state: 'thinking' });
        expect(vi.getTimerCount()).toBe(0);
        expect(mocks.inputs.map((input) => input.value)).toEqual([
            false,
            true,
            false,
            false,
        ]);
        wrapper.unmount();
    });

    it('stops welcome timers offscreen, for reduced motion, and on unmount', async () => {
        const wrapper = render({ animateIdle: true });
        await flushPromises();
        vi.useFakeTimers();
        mocks.options.onLoad();
        intersect([{ isIntersecting: false }]);
        expect(vi.getTimerCount()).toBe(0);
        intersect([{ isIntersecting: true }]);
        expect(mocks.inputs[0].value).toBe(true);
        reduced = true;
        motionChanged();
        expect(vi.getTimerCount()).toBe(0);
        expect(mocks.inputs[0].value).toBe(false);
        reduced = false;
        motionChanged();
        expect(vi.getTimerCount()).toBe(1);
        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('supports welcome and compact status sizes without decorative effects', () => {
        expect(render().classes()).toContain('h-24');
        expect(render({ size: 'sm' }).classes()).toContain('h-20');
        const large = render({ size: 'lg' });
        expect(large.classes()).toContain('h-32');
        expect(large.find('canvas').classes()).toContain('scale-150');
        const status = render({ size: 'status' });
        expect(status.classes()).toContain('h-7');
        expect(status.find('canvas').classes()).not.toContain('scale-150');
        expect(status.attributes('aria-hidden')).toBe('true');
        expect(status.find('.orb-sphere').exists()).toBe(false);
    });
});
