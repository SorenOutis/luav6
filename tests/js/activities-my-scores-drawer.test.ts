/**
 * Mobile "Record" and desktop "Activity Record" buttons open the same drawer.
 * The real Sheet renders full-width on mobile and wider on desktop.
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
            activityRecordEnabled: true,
            activityScores: [
                {
                    seasonName: 'Season 1',
                    exams: [
                        {
                            id: 1,
                            title: 'Scored activity',
                            section_name: 'BSIT 1-A',
                            term: 'Prelims',
                            score: 88.5,
                            total_points: 100,
                            percentage: 88.5,
                            submitted: true,
                            state: 'completed',
                        },
                        {
                            id: 2,
                            title: 'Untaken activity',
                            section_name: 'BSIT 1-A',
                            term: 'Prelims',
                            score: null,
                            total_points: 50,
                            percentage: null,
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

describe('activities hub — Activity Record drawer', () => {
    it('keeps the Record button in the mobile overview', () => {
        wrapper = mountHub();
        const overview = wrapper.get(
            'section[aria-label="Activities overview"]',
        );
        const buttons = overview
            .findAll('button')
            .filter((button) => button.text() === 'Record');
        expect(buttons).toHaveLength(1);
        expect(overview.classes()).toContain('md:hidden');

        // jsdom cannot evaluate responsive CSS; check the mobile entry point
        // and its ancestors for utilities that would hide it below md.
        let element: Element | null = buttons[0].element;
        while (element) {
            expect(element.classList.contains('hidden')).toBe(false);
            element = element.parentElement;
        }
    });

    it.each(['Record', 'Activity Record'])(
        'opens the right-side drawer with every activity score from %s',
        async (label) => {
            wrapper = mountHub();
            const button = wrapper
                .findAll('button')
                .find((b) => b.text() === label);
            expect(button).toBeTruthy();

            await button!.trigger('click');
            await flushPromises();
            await nextTick();
            await flushPromises();

            // The Sheet is teleported to <body> by the reka DialogPortal.
            const sheet = document.querySelector('[data-slot="sheet-content"]');
            expect(sheet).not.toBeNull();

            // Mobile-first width: full-bleed below `sm`, capped on tablet, half-screen on desktop.
            expect(sheet!.className).toContain('w-full');
            expect(sheet!.className).toContain('sm:max-w-xl');
            expect(sheet!.className).toContain('md:max-w-2xl');
            expect(sheet!.className).toContain('lg:w-1/2');
            expect(sheet!.className).toContain('lg:max-w-none');
            // Right-side placement from the Sheet default.
            expect(sheet!.className).toContain('inset-y-0');
            expect(sheet!.className).toContain('right-0');

            const sheetText = (sheet!.textContent ?? '').replace(/\s+/g, ' ');
            expect(sheetText).toContain('Activity Record');
            expect(sheetText).toContain(
                'Your activities and scores, grouped by period.',
            );
            expect(
                sheet!.querySelector('[data-test="activity-status-filters"]'),
            ).toBeNull();
            expect(sheetText).not.toMatch(/activities graded/i);
            expect(sheetText).not.toContain('Unsubmitted deadlines');
            expect(sheetText).not.toContain('Open or pending');

            const table = sheet!.querySelector(
                '[data-test="activity-record-table"]',
            )!;
            const headings = Array.from(
                table.querySelectorAll('th[scope="col"]'),
            );
            expect(headings).toHaveLength(2);
            expect(
                headings.map((heading) => heading.textContent?.trim()),
            ).toEqual(['Activity', 'Score']);
            expect((table as HTMLElement).style.width).toBe('');
            expect(table.classList.contains('w-full')).toBe(true);
            expect(table.parentElement!.className).not.toContain('rounded-xl');
            expect(table.parentElement!.className).not.toContain('bg-card');
            expect(table.classList.contains('table-fixed')).toBe(true);
            expect(table.parentElement!.className).toContain('overflow-x-auto');
            expect(table.parentElement!.getAttribute('tabindex')).toBe('0');
            const totalCell = table.querySelector(
                '[data-test="activity-record-total-cell"]',
            );
            expect(totalCell).not.toBeNull();
            expect(totalCell!.textContent).toContain('89 / 100');
            const summary = sheet!.querySelector(
                '[data-test="activity-record-component-summary"][data-category="written"]',
            );
            expect(summary).toBeNull();
            expect(table.querySelectorAll('tfoot tr')).toHaveLength(1);
            expect(
                table.querySelectorAll(
                    '[data-test="activity-record-total-cell"]',
                ),
            ).toHaveLength(1);
            expect(table.querySelector('tfoot th')?.textContent?.trim()).toBe(
                'Total',
            );
            expect(table.textContent).not.toContain('%');
            const rows = table.querySelectorAll('tbody tr');
            expect(rows).toHaveLength(2);
            expect(
                rows[0]
                    .querySelector('th[scope="row"] button')
                    ?.textContent?.trim(),
            ).toBe('Scored activity');
            expect(rows[0].querySelector('th p')).toBeNull();
            expect(
                rows[1]
                    .querySelector('th[scope="row"] button')
                    ?.textContent?.trim(),
            ).toBe('Untaken activity');
            expect(rows[1].querySelector('th p')).toBeNull();
            const cells = table.querySelectorAll(
                '[data-test="activity-record-cell"]',
            );
            expect(cells).toHaveLength(2);
            const scored = (cells[0].textContent ?? '').replace(/\s+/g, ' ');
            expect(scored.trim()).toBe('89 / 100');
            expect(table.textContent).not.toContain('Review Answers');
            expect(table.textContent).not.toContain('Deadline');
            expect(
                rows[0].querySelector('th button')!.getAttribute('aria-label'),
            ).toBe('Review answers for Scored activity');
            const untaken = (cells[1].textContent ?? '').replace(/\s+/g, ' ');
            expect(untaken.trim()).toBe('— / 50');
            expect(table.querySelectorAll('td button')).toHaveLength(0);
            expect(
                rows[1].querySelector('th button')!.getAttribute('aria-label'),
            ).toBe('Open Untaken activity');

            expect(
                sheet!.querySelector('[data-test="activity-status-filters"]'),
            ).toBeNull();
            expect(
                sheet!.querySelector(
                    'input[placeholder="Search activity by title or section..."]',
                ),
            ).not.toBeNull();
            expect(
                (
                    sheet!.querySelector(
                        '[data-test="activity-record-table"]',
                    ) as HTMLElement
                ).style.width,
            ).toBe('');
            expect(
                sheet!.querySelectorAll('[data-test="activity-record-cell"]'),
            ).toHaveLength(2);
            expect(
                sheet!.querySelector(
                    '[data-test="activity-record-table"] tbody th[scope="row"]',
                )!.textContent,
            ).toContain('Scored activity');

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
        },
    );
});
