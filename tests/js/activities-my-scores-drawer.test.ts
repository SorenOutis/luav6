/**
 * The "My Scores" button and its right-side drawer must work in BOTH
 * viewports — the button is not gated behind a desktop-only class and the
 * Sheet renders full-width on mobile (`w-full sm:max-w-md`).
 */
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(async () => ({
            data: {
                exam: {
                    id: 7,
                    parts: [{ id: 101, title: 'Part I', questions: [] }],
                },
                submissions: [],
            },
        })),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        props: ['href'],
        setup(props: any, { slots }: any) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
    router: {
        reload: vi.fn(),
        visit: vi.fn(),
        // The hub listens for `navigate` to follow calendar deep links; the
        // real router.on() hands back its own remover.
        on: vi.fn(() => vi.fn()),
    },
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
    usePage: () => ({
        props: {
            auth: { user: { id: 1, public_id: 'test-user' } },
            onboarding: { tours: { 'activities-hub': 'done' } },
        },
    }),
}));

vi.mock('@motionone/vue', () => ({
    Motion: defineComponent({
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    }),
}));

const lenisStop = vi.fn();
const lenisStart = vi.fn();
vi.mock('@/composables/useLenis', () => ({
    getLenis: vi.fn(() => ({ stop: lenisStop, start: lenisStart })),
}));

vi.mock('@/routes/exams', () => ({
    show: (id: number) => ({ url: `/exams/${id}` }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    }),
}));

// Deliberately NOT stubbed: the real reka-ui Sheet, so the test exercises
// the same portal/overlay that ships in the app.

const Activities = (await import('@/pages/Activities/Index.vue')).default;

const stubs = {
    Head: { render: () => null },
    Link: {
        props: ['href'],
        setup(props: any, { slots }: any) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    },
    Motion: {
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    },
    AppLayout: {
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    },
    OnboardingTour: { render: () => null },
    ResponsiveModal: { render: () => null },
};

const mountHub = () =>
    mount(Activities, {
        props: {
            examsBySeason: [] as any,
            examPagination: { hasMore: false, nextCursor: null },
            sectionTabs: [{ key: 'all', label: 'All sections', count: 2 }],
            hubStats: { exams: { total: 2, pending: 1, completed: 1 } },
            activityScores: [
                {
                    seasonName: 'Season 1',
                    exams: [
                        {
                            id: 1,
                            title: 'Scored activity',
                            section_name: 'BSIT 1-A',
                            score: 88.5,
                            submitted: true,
                            state: 'completed',
                        },
                        {
                            id: 2,
                            title: 'Untaken activity',
                            section_name: 'BSIT 1-A',
                            score: null,
                            submitted: false,
                            state: 'open',
                        },
                    ],
                },
            ] as any,
        },
        global: { stubs },
    });

let wrapper: ReturnType<typeof mountHub> | null = null;

beforeEach(() => {
    lenisStop.mockClear();
    lenisStart.mockClear();
    document.body.style.overflow = '';
    // jsdom doesn't implement element scrolling.
    Element.prototype.scrollTo = vi.fn() as any;
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.style.overflow = '';
});

describe('activities hub — My Scores drawer', () => {
    it('shows the My Scores button in mobile view (no viewport gating)', () => {
        wrapper = mountHub();
        const buttons = wrapper
            .findAll('button')
            .map((b) => b.text())
            .filter((t) => t.includes('My Scores'));
        expect(buttons.length).toBe(1);

        // The button must not carry any utility that hides it on mobile —
        // otherwise the drawer would be unreachable below the `sm` breakpoint.
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('My Scores'));
        expect(button?.classes()).not.toContain('hidden');
        expect(button?.classes()).not.toContain('sm:hidden');
        expect(button?.classes()).not.toContain('md:hidden');
    });

    it('opens the right-side drawer with every activity score when clicked', async () => {
        wrapper = mountHub();
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('My Scores'));
        expect(button).toBeTruthy();

        await button!.trigger('click');
        await flushPromises();
        await nextTick();
        await flushPromises();

        // The Sheet is teleported to <body> by the reka DialogPortal.
        const sheet = document.querySelector('[data-slot="sheet-content"]');
        expect(sheet).not.toBeNull();

        // Mobile-first width: full-bleed below `sm`, capped on desktop.
        expect(sheet!.className).toContain('w-full');
        expect(sheet!.className).toContain('sm:max-w-md');
        // Right-side placement from the Sheet default.
        expect(sheet!.className).toContain('inset-y-0');
        expect(sheet!.className).toContain('right-0');

        // Header + summary.
        const bodyText = document.body.textContent ?? '';
        expect(bodyText).toContain('My Scores');
        expect(bodyText).toContain('1 of 2 activities graded');

        // Every activity from the prop, with its score or a placeholder.
        expect(bodyText).toContain('Scored activity');
        expect(bodyText).toContain('88.5');
        expect(bodyText).toContain('Untaken activity');
        expect(bodyText).toContain('—');

        // Lenis is stopped while the drawer is open.
        expect(lenisStop).toHaveBeenCalled();

        // Closing via the built-in X (sr-only "Close") removes the sheet and
        // restores Lenis.
        const close = Array.from(document.querySelectorAll('button')).find(
            (b) => b.textContent?.trim() === 'Close',
        );
        expect(close).toBeTruthy();
        close!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();
        await nextTick();
        await flushPromises();
        await nextTick();

        expect(
            document.querySelector('[data-slot="sheet-content"]'),
        ).toBeNull();
        expect(lenisStart).toHaveBeenCalled();
    });
});
