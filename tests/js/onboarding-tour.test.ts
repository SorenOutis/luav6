import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OnboardingTour from '@/components/OnboardingTour.vue';
import {
    getTourStatus,
    setTourStatus,
    resetTourStatus,
} from '@/lib/onboarding';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { public_id: 'user-123' } },
        },
    }),
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

    it('uses localStorage so completion is per device', () => {
        setTourStatus('grades', 'done', 'u1');
        const keys = Object.keys(window.localStorage);
        expect(keys.some((k) => k.startsWith('onboarding:'))).toBe(true);
    });
});

describe('OnboardingTour', () => {
    beforeEach(() => {
        window.localStorage.clear();
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
        expect(src).toContain('data-tour="dashboard-level-card"');
        expect(src).toContain('data-tour="dashboard-streak-card"');
        expect(src).toContain('data-tour="dashboard-leaderboard"');
    });
});
