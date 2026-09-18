import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import ProgressCard from '@/components/dashboard/ProgressCard.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import {
    getTourStatus,
    setTourStatus,
    resetTourStatus,
} from '@/lib/onboarding';

/** Account-level onboarding prop the mocked page exposes. */
const serverTours: { tours: Record<string, string> } = { tours: {} };
const routerPost = vi.fn();
const routerDelete = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { public_id: 'user-123' } },
            onboarding: serverTours,
        },
    }),
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        delete: (...args: unknown[]) => routerDelete(...args),
    },
}));

/** Lenis singleton stand-in: null = no smooth-scroll engine (native path). */
const mockGetLenis = vi.hoisted(() => vi.fn());
vi.mock('@/composables/useLenis', () => ({
    getLenis: () => mockGetLenis(),
}));

const flushTimers = async (ms: number) => {
    await vi.advanceTimersByTimeAsync(ms);
};

const mountTour = (overrides: Record<string, unknown> = {}) =>
    mount(OnboardingTour, {
        attachTo: document.body,
        props: {
            tourId: 'test-tour',
            steps: [
                { id: 'a', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'b',
                    title: 'Missing target',
                    body: 'Should be skipped.',
                    target: 'does-not-exist',
                },
                { id: 'c', title: 'Outro', body: 'Last step.' },
            ],
            startDelay: 0,
            ...overrides,
        },
    });

describe('onboarding persistence (lib/onboarding)', () => {
    beforeEach(() => {
        window.localStorage.clear();
        serverTours.tours = {};
        routerPost.mockClear();
        routerDelete.mockClear();
    });

    it('stores and reads status scoped per user', () => {
        expect(getTourStatus('dashboard', 'u1')).toBeNull();

        setTourStatus('dashboard', 'skipped', 'u1');
        expect(getTourStatus('dashboard', 'u1')).toBe('skipped');
        // Different user on the same device still gets the tour.
        expect(getTourStatus('dashboard', 'u2')).toBeNull();

        setTourStatus('dashboard', 'done', 'u2');
        expect(getTourStatus('dashboard', 'u2')).toBe('done');

        resetTourStatus('dashboard', 'u1');
        expect(getTourStatus('dashboard', 'u1')).toBeNull();
    });

    it('uses localStorage so completion survives an offline/failed request', () => {
        setTourStatus('grades', 'done', 'u1');
        const keys = Object.keys(window.localStorage);
        expect(keys.some((k) => k.startsWith('onboarding:'))).toBe(true);
    });

    it('persists resolution to the account', () => {
        setTourStatus('grades', 'done', 'u1');

        expect(routerPost).toHaveBeenCalledTimes(1);
        expect(routerPost.mock.calls[0][0]).toBe('/onboarding/grades');
        expect(routerPost.mock.calls[0][1]).toEqual({ status: 'done' });
    });

    it('treats the account record as resolved even on a fresh device', () => {
        // Nothing in localStorage — a brand new browser for this user.
        expect(getTourStatus('grades', 'u1')).toBeNull();

        expect(
            getTourStatus('grades', 'u1', { tours: { grades: 'skipped' } }),
        ).toBe('skipped');
        expect(
            getTourStatus('grades', 'u1', { tours: { grades: 'done' } }),
        ).toBe('done');
        expect(
            getTourStatus('chats', 'u1', { tours: { grades: 'done' } }),
        ).toBe(null);
    });

    it('ignores malformed account values', () => {
        expect(
            getTourStatus('grades', 'u1', { tours: { grades: 'nonsense' } }),
        ).toBeNull();
        expect(getTourStatus('grades', 'u1', { tours: null })).toBeNull();
        expect(getTourStatus('grades', 'u1', null)).toBeNull();
    });

    it('clears both layers on reset', () => {
        setTourStatus('grades', 'done', 'u1');
        resetTourStatus('grades', 'u1');

        expect(getTourStatus('grades', 'u1')).toBeNull();
        expect(routerDelete).toHaveBeenCalledWith(
            '/onboarding/grades',
            expect.anything(),
        );
    });
});

describe('OnboardingTour', () => {
    beforeEach(() => {
        window.localStorage.clear();
        serverTours.tours = {};
        routerPost.mockClear();
        routerDelete.mockClear();
        document.body.innerHTML = '';
        vi.useFakeTimers();
    });

    it('auto-starts for a fresh device and skips steps with missing targets', async () => {
        const wrapper = mountTour();
        await flushTimers(50);

        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).not.toBeNull();
        // Missing-target step dropped: 3 declared → 2 rendered.
        expect(document.body.textContent).toContain('1 of 2');
        expect(document.body.textContent).toContain('Welcome');
        expect(
            document.querySelector('[data-testid="onboarding-fox"]'),
        ).not.toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('does not start when the tour was already completed on this device', async () => {
        setTourStatus('test-tour', 'done', 'user-123');

        const wrapper = mountTour();
        await flushTimers(50);

        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('does not start when the account already finished it on another device', async () => {
        // Empty localStorage (new browser) but the account says it's done.
        serverTours.tours = { 'test-tour': 'done' };

        const wrapper = mountTour();
        await flushTimers(50);

        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('does not start when the account record says it was skipped', async () => {
        serverTours.tours = { 'test-tour': 'skipped' };

        const wrapper = mountTour();
        await flushTimers(50);

        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('records the resolution on the account when skipped', async () => {
        const wrapper = mountTour();
        await flushTimers(50);

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-skip"]',
            )!
            .click();
        await flushTimers(10);

        expect(routerPost).toHaveBeenCalledTimes(1);
        expect(routerPost.mock.calls[0][0]).toBe('/onboarding/test-tour');
        expect(routerPost.mock.calls[0][1]).toEqual({ status: 'skipped' });

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('persists "skipped" when the user skips', async () => {
        const wrapper = mountTour();
        await flushTimers(50);

        const skipButton = document.querySelector<HTMLButtonElement>(
            '[data-testid="onboarding-skip"]',
        );
        expect(skipButton).not.toBeNull();
        skipButton!.click();
        await flushTimers(10);

        expect(getTourStatus('test-tour', 'user-123')).toBe('skipped');
        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('persists "done" after walking through every step', async () => {
        const wrapper = mountTour();
        await flushTimers(50);

        const clickNext = async () => {
            document
                .querySelector<HTMLButtonElement>(
                    '[data-testid="onboarding-next"]',
                )!
                .click();
            await flushTimers(10);
        };

        await clickNext(); // step 1 → 2
        expect(document.body.textContent).toContain('2 of 2');
        await clickNext(); // Done

        expect(getTourStatus('test-tour', 'user-123')).toBe('done');
        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('waits for canStart before appearing', async () => {
        const wrapper = mountTour({ canStart: false });
        await flushTimers(50);
        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).toBeNull();

        await wrapper.setProps({ canStart: true });
        await flushTimers(50);
        expect(
            document.querySelector('[data-testid="onboarding-tour"]'),
        ).not.toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('keeps the mobile card mounted and shows the next targeted step', async () => {
        const originalWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        const target = document.createElement('div');
        target.dataset.tour = 'mobile-target';
        target.getBoundingClientRect = () => ({
            x: 12,
            y: 160,
            top: 160,
            right: 378,
            bottom: 240,
            left: 12,
            width: 366,
            height: 80,
            toJSON: () => ({}),
        });
        target.scrollIntoView = vi.fn();
        document.body.appendChild(target);

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Mobile feature',
                    body: 'The next step.',
                    target: 'mobile-target',
                },
            ],
        });
        await flushTimers(50);

        const firstCard = document.querySelector<HTMLElement>('.ot-card');
        expect(firstCard).not.toBeNull();

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        const nextCard = document.querySelector<HTMLElement>('.ot-card');
        expect(nextCard).toBe(firstCard);
        expect(nextCard?.textContent).toContain('2 of 2');
        expect(nextCard?.textContent).toContain('Mobile feature');
        expect(nextCard?.style.bottom).toContain('4.75rem');
        expect(target.scrollIntoView).toHaveBeenCalled();

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: originalWidth,
        });
        vi.useRealTimers();
    });

    it('advances the same card to the next step on desktop', async () => {
        const originalWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 1280,
        });

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                { id: 'outro', title: 'All set', body: 'The next step.' },
            ],
        });
        await flushTimers(50);

        expect(document.body.textContent).toContain('Welcome');

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        const nextCard = document.querySelector<HTMLElement>('.ot-card');
        expect(nextCard).not.toBeNull();
        expect(nextCard?.textContent).toContain('2 of 2');
        expect(nextCard?.textContent).toContain('All set');
        expect(nextCard?.textContent).toContain('The next step.');

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: originalWidth,
        });
        vi.useRealTimers();
    });

    it('emits the step id whenever the visible step changes', async () => {
        const wrapper = mountTour();
        await flushTimers(50);

        // Tour starts on step 'a' ('b' has a missing target and is dropped).
        expect(wrapper.emitted('step')?.map((call) => call[0])).toEqual(['a']);

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        expect(wrapper.emitted('step')?.map((call) => call[0])).toEqual([
            'a',
            'c',
        ]);

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('pins mobile targets to the viewport top so the docked card cannot cover them', async () => {
        const originalWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        const target = document.createElement('div');
        target.dataset.tour = 'mobile-pinned-target';
        target.getBoundingClientRect = () => ({
            x: 12,
            y: 300,
            top: 300,
            right: 378,
            bottom: 700,
            left: 12,
            width: 366,
            height: 400,
            toJSON: () => ({}),
        });
        target.scrollIntoView = vi.fn();
        document.body.appendChild(target);

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Mobile feature',
                    body: 'Pinned to the top.',
                    target: 'mobile-pinned-target',
                },
            ],
        });
        await flushTimers(50);

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        expect(target.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'start' }),
        );

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: originalWidth,
        });
        vi.useRealTimers();
    });

    it('keeps centering targets on desktop', async () => {
        const originalWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 1280,
        });

        const target = document.createElement('div');
        target.dataset.tour = 'desktop-centered-target';
        target.getBoundingClientRect = () => ({
            x: 100,
            y: 200,
            top: 200,
            right: 500,
            bottom: 400,
            left: 100,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        });
        target.scrollIntoView = vi.fn();
        document.body.appendChild(target);

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Desktop feature',
                    body: 'Centered.',
                    target: 'desktop-centered-target',
                },
            ],
        });
        await flushTimers(50);

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        expect(target.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'center' }),
        );

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: originalWidth,
        });
        vi.useRealTimers();
    });
});

describe('mobile target overrides', () => {
    beforeEach(() => {
        window.localStorage.clear();
        serverTours.tours = {};
        routerPost.mockClear();
        routerDelete.mockClear();
        document.body.innerHTML = '';
        vi.useFakeTimers();
    });

    const mockRect = (top: number, height: number) => ({
        x: 12,
        y: top,
        top,
        right: 378,
        bottom: top + height,
        left: 12,
        width: 366,
        height,
        toJSON: () => ({}),
    });

    const addTarget = (name: string, top: number, height: number) => {
        const el = document.createElement('div');
        el.dataset.tour = name;
        el.getBoundingClientRect = () => mockRect(top, height);
        el.scrollIntoView = vi.fn();
        document.body.appendChild(el);
        return el as unknown as HTMLElement & {
            scrollIntoView: ReturnType<typeof vi.fn>;
        };
    };

    const withWidth = (value: number, original: number) => {
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value,
        });
        return () =>
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                value: original,
            });
    };

    const goNext = async () => {
        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);
    };

    it('prefers the mobile anchor on small screens so tall cards stay visible', async () => {
        const originalWidth = window.innerWidth;
        const restoreWidth = withWidth(390, originalWidth);

        const desktopEl = addTarget('desktop-card', 300, 400);
        const mobileEl = addTarget('desktop-card-tabs', 300, 56);

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Feature tabs',
                    body: 'The tabs stay visible.',
                    target: 'desktop-card',
                    mobileTarget: 'desktop-card-tabs',
                },
            ],
        });
        await flushTimers(50);
        // Welcome step still shows the fox.
        expect(
            document.querySelector('[data-testid="onboarding-fox"]'),
        ).not.toBeNull();

        await goNext();

        // The compact mobile anchor is scrolled to the top and spotlit —
        // not the tall desktop card behind the docked tour card.
        expect(mobileEl.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'start' }),
        );
        expect(desktopEl.scrollIntoView).not.toHaveBeenCalled();
        const spot = document.querySelector<HTMLElement>('.ot-spotlight');
        expect(spot?.style.top).toBe(`${300 - 8}px`);
        expect(spot?.style.height).toBe(`${56 + 16}px`);
        // The fox row hides on mobile targeted steps to free up room.
        expect(
            document.querySelector('[data-testid="onboarding-fox"]'),
        ).toBeNull();
        // The card docks narrow on the left so the target stays visible
        // beside it instead of behind a full-width sheet.
        const dockedCard = document.querySelector<HTMLElement>('.ot-card');
        expect(dockedCard?.classList.contains('left-3')).toBe(true);
        // Narrow sheet (jsdom serializes the calc arguments in its own
        // order, so match loosely).
        expect(dockedCard?.style.width).toContain('20rem');
        expect(dockedCard?.style.bottom).toContain('4.75rem');

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('falls back to the desktop target when the mobile anchor is missing', async () => {
        const originalWidth = window.innerWidth;
        const restoreWidth = withWidth(390, originalWidth);

        const desktopEl = addTarget('desktop-card', 300, 400);

        const wrapper = mountTour({
            steps: [
                {
                    id: 'feature',
                    title: 'Feature',
                    body: 'Fallback.',
                    target: 'desktop-card',
                    mobileTarget: 'does-not-exist',
                },
            ],
        });
        await flushTimers(50);

        expect(desktopEl.scrollIntoView).toHaveBeenCalled();
        expect(document.body.textContent).toContain('1 of 1');

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('keeps the desktop target on wide screens even with a mobile anchor', async () => {
        const originalWidth = window.innerWidth;
        const restoreWidth = withWidth(1280, originalWidth);

        const desktopEl = addTarget('desktop-card', 200, 400);
        const mobileEl = addTarget('desktop-card-tabs', 200, 56);

        const wrapper = mountTour({
            steps: [
                {
                    id: 'feature',
                    title: 'Feature',
                    body: 'Desktop uses the full card.',
                    target: 'desktop-card',
                    mobileTarget: 'desktop-card-tabs',
                },
            ],
        });
        await flushTimers(50);

        expect(desktopEl.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'center' }),
        );
        expect(mobileEl.scrollIntoView).not.toHaveBeenCalled();
        // Fox stays visible on desktop targeted steps.
        expect(
            document.querySelector('[data-testid="onboarding-fox"]'),
        ).not.toBeNull();

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });
});

describe('lenis-aware scrolling', () => {
    beforeEach(() => {
        window.localStorage.clear();
        serverTours.tours = {};
        routerPost.mockClear();
        routerDelete.mockClear();
        document.body.innerHTML = '';
        mockGetLenis.mockReturnValue(null);
        vi.useFakeTimers();
    });

    const withWidth = (value: number) => {
        const original = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value,
        });
        return () =>
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                value: original,
            });
    };

    const addTarget = (name: string, top: number, height: number) => {
        const el = document.createElement('div');
        el.dataset.tour = name;
        el.getBoundingClientRect = () => ({
            x: 12,
            y: top,
            top,
            right: 378,
            bottom: top + height,
            left: 12,
            width: 366,
            height,
            toJSON: () => ({}),
        });
        el.scrollIntoView = vi.fn();
        document.body.appendChild(el);
        return el;
    };

    const goNext = async () => {
        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);
    };

    const mountSteppedTour = () =>
        mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Feature',
                    body: 'Scrolled into view.',
                    target: 'lenis-target',
                },
            ],
        });

    it('scrolls through Lenis on mobile instead of fighting it', async () => {
        const restoreWidth = withWidth(390);
        const scrollToMock = vi.fn();
        mockGetLenis.mockReturnValue({
            scrollTo: scrollToMock,
            isStopped: false,
        });

        const target = addTarget('lenis-target', 300, 56);
        const wrapper = mountSteppedTour();
        await flushTimers(50);
        await goNext();

        expect(scrollToMock).toHaveBeenCalledWith(
            target,
            expect.objectContaining({ offset: -12 }),
        );
        expect(target.scrollIntoView).not.toHaveBeenCalled();

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('centers through Lenis on desktop', async () => {
        const restoreWidth = withWidth(1280);
        const scrollToMock = vi.fn();
        mockGetLenis.mockReturnValue({
            scrollTo: scrollToMock,
            isStopped: false,
        });

        // jsdom viewport height is 768: center offset = -(384 - 100).
        const target = addTarget('lenis-target', 200, 200);
        const wrapper = mountSteppedTour();
        await flushTimers(50);
        await goNext();

        expect(scrollToMock).toHaveBeenCalledWith(
            target,
            expect.objectContaining({ offset: -284 }),
        );
        expect(target.scrollIntoView).not.toHaveBeenCalled();

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('falls back to native scrolling when Lenis is stopped', async () => {
        const restoreWidth = withWidth(390);
        const scrollToMock = vi.fn();
        mockGetLenis.mockReturnValue({
            scrollTo: scrollToMock,
            isStopped: true,
        });

        const target = addTarget('lenis-target', 300, 56);
        const wrapper = mountSteppedTour();
        await flushTimers(50);
        await goNext();

        expect(target.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'start' }),
        );
        expect(scrollToMock).not.toHaveBeenCalled();

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('nudges the page after settling so the spot clears the docked card', async () => {
        const restoreWidth = withWidth(390);
        mockGetLenis.mockReturnValue(null);
        const scrollToMock = vi.fn();
        Object.defineProperty(window, 'scrollTo', {
            configurable: true,
            writable: true,
            value: scrollToMock,
        });

        addTarget('lenis-target', 500, 56);
        const wrapper = mountSteppedTour();
        await flushTimers(50);

        // Tall docked card overlapping the spot (500–556 vs card 400–650).
        const card = document.querySelector<HTMLElement>('.ot-card');
        expect(card).not.toBeNull();
        card!.getBoundingClientRect = () => ({
            x: 12,
            y: 400,
            top: 400,
            right: 332,
            bottom: 650,
            left: 12,
            width: 320,
            height: 250,
            toJSON: () => ({}),
        });

        await goNext();
        await flushTimers(700);

        // Overlap delta 556 - 400 + 8 = 164, applied as an instant jump.
        expect(scrollToMock).toHaveBeenCalledWith(
            expect.objectContaining({ top: 164 }),
        );

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('pulls an overshot spot back into the free band', async () => {
        const restoreWidth = withWidth(390);
        mockGetLenis.mockReturnValue(null);
        const scrollToMock = vi.fn();
        Object.defineProperty(window, 'scrollTo', {
            configurable: true,
            writable: true,
            value: scrollToMock,
        });

        // Spot scrolled too far (top above the viewport).
        addTarget('lenis-target', -30, 56);
        const wrapper = mountSteppedTour();
        await flushTimers(50);

        const card = document.querySelector<HTMLElement>('.ot-card');
        expect(card).not.toBeNull();
        card!.getBoundingClientRect = () => ({
            x: 12,
            y: 400,
            top: 400,
            right: 332,
            bottom: 650,
            left: 12,
            width: 320,
            height: 250,
            toJSON: () => ({}),
        });

        await goNext();
        await flushTimers(700);

        // Delta -30 - 12 = -42, clamped to the top of the page.
        expect(scrollToMock).toHaveBeenCalledWith(
            expect.objectContaining({ top: 0 }),
        );

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('leaves a tall spot pinned to the top instead of pushing it off-screen', async () => {
        const restoreWidth = withWidth(390);
        mockGetLenis.mockReturnValue(null);
        const scrollToMock = vi.fn();
        Object.defineProperty(window, 'scrollTo', {
            configurable: true,
            writable: true,
            value: scrollToMock,
        });

        // Taller than the free band but already top-pinned: nothing to do.
        addTarget('lenis-target', 12, 500);
        const wrapper = mountSteppedTour();
        await flushTimers(50);

        const card = document.querySelector<HTMLElement>('.ot-card');
        expect(card).not.toBeNull();
        card!.getBoundingClientRect = () => ({
            x: 12,
            y: 400,
            top: 400,
            right: 332,
            bottom: 650,
            left: 12,
            width: 320,
            height: 250,
            toJSON: () => ({}),
        });

        await goNext();
        await flushTimers(700);

        expect(scrollToMock).not.toHaveBeenCalled();

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });

    it('runs the settle check from the Lenis onComplete hook', async () => {
        const restoreWidth = withWidth(390);
        const scrollToMock = vi.fn();
        mockGetLenis.mockReturnValue({
            scrollTo: scrollToMock,
            isStopped: false,
        });

        addTarget('lenis-target', 500, 56);
        const wrapper = mountSteppedTour();
        await flushTimers(50);

        const card = document.querySelector<HTMLElement>('.ot-card');
        expect(card).not.toBeNull();
        card!.getBoundingClientRect = () => ({
            x: 12,
            y: 400,
            top: 400,
            right: 332,
            bottom: 650,
            left: 12,
            width: 320,
            height: 250,
            toJSON: () => ({}),
        });

        await goNext();

        const onComplete = scrollToMock.mock.calls[0][1]?.onComplete;
        expect(typeof onComplete).toBe('function');
        scrollToMock.mockClear();
        onComplete();

        // Same 164px instant nudge, driven by animation completion.
        expect(scrollToMock).toHaveBeenCalledWith(
            164,
            expect.objectContaining({ immediate: true }),
        );

        wrapper.unmount();
        restoreWidth();
        vi.useRealTimers();
    });
});

describe('narrow viewports dock without touch', () => {
    beforeEach(() => {
        window.localStorage.clear();
        serverTours.tours = {};
        routerPost.mockClear();
        routerDelete.mockClear();
        document.body.innerHTML = '';
        mockGetLenis.mockReturnValue(null);
        vi.useFakeTimers();
    });

    it('docks below 1024px even without touch (small desktop windows)', async () => {
        const originalWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 800,
        });

        const target = document.createElement('div');
        target.dataset.tour = 'narrow-window-target';
        target.getBoundingClientRect = () => ({
            x: 24,
            y: 200,
            top: 200,
            right: 776,
            bottom: 320,
            left: 24,
            width: 752,
            height: 120,
            toJSON: () => ({}),
        });
        target.scrollIntoView = vi.fn();
        document.body.appendChild(target);

        const wrapper = mountTour({
            steps: [
                { id: 'welcome', title: 'Welcome', body: 'Intro step.' },
                {
                    id: 'feature',
                    title: 'Narrow feature',
                    body: 'Docked, not floating.',
                    target: 'narrow-window-target',
                },
            ],
        });
        await flushTimers(50);

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="onboarding-next"]',
            )!
            .click();
        await flushTimers(10);

        // Pinned to the top and docked bottom-left, like mobile.
        expect(target.scrollIntoView).toHaveBeenCalledWith(
            expect.objectContaining({ block: 'start' }),
        );
        const dockedCard = document.querySelector<HTMLElement>('.ot-card');
        expect(dockedCard?.classList.contains('left-3')).toBe(true);
        expect(dockedCard?.style.bottom).toContain('4.75rem');
        expect(
            document.querySelector('[data-testid="onboarding-fox"]'),
        ).toBeNull();

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: originalWidth,
        });
        vi.useRealTimers();
    });
});

describe('progress card tab control', () => {
    const mountProgressCard = () =>
        mount(ProgressCard, {
            attachTo: document.body,
            props: {
                userStats: {
                    totalXP: 100,
                    level: 2,
                    currentXP: 40,
                    maxXPForLevel: 100,
                    rank: 'Rookie',
                    rankNumber: 3,
                    totalPlayers: 10,
                    achievements: 1,
                    points: 5,
                    streak: 4,
                    longestStreak: 9,
                    joinedAt: '2026-01-01',
                },
            },
            global: {
                stubs: {
                    LevelProgressCard: {
                        template: '<div class="stub-level" />',
                    },
                    StreakCard: { template: '<div class="stub-streak" />' },
                    SeasonProgressBand: {
                        template: '<div class="stub-season" />',
                    },
                },
            },
        });

    it('starts on XP and flips panes via the exposed setter', async () => {
        const wrapper = mountProgressCard();
        const selected = () =>
            wrapper
                .findAll('[role="tab"]')
                .findIndex((tab) => tab.attributes('aria-selected') === 'true');

        expect(selected()).toBe(0);

        (
            wrapper.vm as unknown as {
                setActivePane: (pane: 'xp' | 'streak' | 'season') => void;
            }
        ).setActivePane('streak');
        await nextTick();
        expect(selected()).toBe(1);

        (
            wrapper.vm as unknown as {
                setActivePane: (pane: 'xp' | 'streak' | 'season') => void;
            }
        ).setActivePane('season');
        await nextTick();
        expect(selected()).toBe(2);

        wrapper.unmount();
    });
});

describe('page wiring', () => {
    const read = (rel: string) =>
        readFileSync(join(process.cwd(), rel), 'utf8');

    it('mounts the tour on dashboard, activities, grades, chats and appearance', () => {
        for (const [file, tourId] of [
            ['resources/js/pages/Dashboard.vue', 'dashboard'],
            ['resources/js/pages/Exam.vue', 'activities'],
            ['resources/js/pages/Grades.vue', 'grades'],
            ['resources/js/pages/Chats.vue', 'chats'],
            ['resources/js/pages/settings/Appearance.vue', 'appearance'],
        ] as const) {
            const src = read(file);
            expect(src, file).toContain('OnboardingTour');
            expect(src, file).toContain(`tour-id="${tourId}"`);
        }
    });

    it('tags the dashboard XP history and streak cards as tour targets', () => {
        const src = read('resources/js/pages/Dashboard.vue');
        // Desktop: the consolidated progress card is the shared anchor for
        // the level / streak / season tour steps.
        expect(src).toContain('data-tour="dashboard-progress"');
        expect(src).toContain('data-tour="dashboard-leaderboard"');
        // Mobile keeps per-card anchors on the streak and XP history cards.
        const mobile = read(
            'resources/js/components/dashboard/MobileDashboard.vue',
        );
        expect(mobile).toContain('data-tour="dashboard-streak-card"');
        expect(mobile).toContain('data-tour="dashboard-level-card"');
    });

    it('flips the progress card tab as the dashboard tour reaches each pane', () => {
        const src = read('resources/js/pages/Dashboard.vue');
        expect(src).toContain('@step="onDashboardTourStep"');
        expect(src).toContain('ref="progressCardRef"');
        expect(src).toContain("streak: 'streak'");
        expect(src).toContain("season: 'season'");
        // The tall card keeps its desktop anchor, but mobile spotlights the
        // compact tab strip so the docked card cannot cover it.
        expect(src).toContain("mobileTarget: 'dashboard-progress-tabs'");
        expect(src).toContain("mobileTarget: 'dashboard-activity-header'");
        const card = read('resources/js/components/dashboard/ProgressCard.vue');
        expect(card).toContain('setActivePane');
        expect(card).toContain('data-tour="dashboard-progress-tabs"');
        expect(src).toContain('data-tour="dashboard-activity-header"');
    });

    it('offers a replay escape hatch to re-verify the dashboard tour', () => {
        const src = read('resources/js/pages/Dashboard.vue');
        expect(src).toContain('tour=replay');
        expect(src).toContain('resetTourStatus');
        expect(src).toContain('ref="tourRef"');
    });
});
