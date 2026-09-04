/**
 * Regression test: opening and closing the Activities hub review modal must
 * not permanently scroll-lock the page.
 *
 * The page used to set `document.body.style.overflow = 'hidden'` in its
 * `watch(showReviewModal)` before reka-ui's DialogOverlay mounted. Reka's
 * shared body-scroll lock snapshots the CURRENT body overflow the first time
 * it locks, so it saved `'hidden'` as the "previous" value and restored it
 * when the dialog closed — leaving the whole page unable to scroll until a
 * reload. The watch now only locks body scroll on mobile (where the custom
 * bottom sheet has no reka overlay); on desktop reka owns the lock.
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
                    parts: [
                        {
                            id: 101,
                            title: 'Part I',
                            questions: [],
                        },
                    ],
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

// Deliberately NOT stubbed: ResponsiveModal (real reka-ui Dialog) and
// useMobile (real — jsdom's default 1024px viewport is desktop).

const Activities = (await import('@/pages/Activities/Index.vue')).default;

const closedExam = {
    id: 7,
    title: 'Midterm Exam',
    description: 'Closed exam with results',
    exam_date: '2026-08-01',
    exam_date_iso: '2026-08-01T00:00:00+08:00',
    duration_minutes: 60,
    status: 'closed',
    url: null,
    submitted_parts_count: 1,
    total_parts: 1,
    is_locked: true,
    has_submissions: true,
    results_available: true,
    submissions: [
        {
            id: 1,
            exam_part_id: 101,
            answers: [],
            status: 'submitted',
            score: '8.0',
        },
    ],
    parts: [
        {
            id: 101,
            title: 'Part I',
            instructions: null,
            type: 'section',
            points: 1,
            questions: [],
        },
    ],
    section_name: 'Section A',
    season_name: 'Season 1',
};

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
};

const mountHub = () =>
    mount(Activities, {
        props: {
            examsBySeason: [
                { seasonName: 'Season 1', exams: [closedExam] },
            ] as any,
            examPagination: { hasMore: false, nextCursor: null },
            sectionTabs: [{ key: 'all', label: 'All sections', count: 1 }],
            hubStats: { exams: { total: 1, pending: 0, completed: 1 } },
        },
        global: { stubs },
    });

beforeEach(() => {
    lenisStop.mockClear();
    lenisStart.mockClear();
    document.body.style.overflow = '';
    // jsdom doesn't implement element scrolling.
    Element.prototype.scrollTo = vi.fn() as any;
});

afterEach(() => {
    document.body.style.overflow = '';
});

describe('activities hub — review modal scroll lock', () => {
    it('restores body scrolling after the review modal closes (desktop)', async () => {
        const wrapper = mountHub();
        await flushPromises();

        const reviewButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Review results'));
        expect(reviewButton).toBeTruthy();

        await reviewButton!.trigger('click');
        await flushPromises();
        await nextTick();
        await flushPromises();

        // While open, the page is locked (reka owns this on desktop).
        expect(document.body.style.overflow).toBe('hidden');
        expect(lenisStop).toHaveBeenCalled();

        // The real reka Dialog teleports to document.body — look there.
        const closeButton = Array.from(
            document.querySelectorAll('button'),
        ).find((b) => b.textContent?.trim() === 'Close');
        expect(closeButton).toBeTruthy();

        closeButton!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();
        await nextTick();
        await flushPromises();
        await nextTick();

        // Regression: reka restores what it snapshotted. It must be '' —
        // before the fix it snapshotted our premature 'hidden' and left the
        // body permanently locked.
        expect(document.body.style.overflow).toBe('');
        expect(lenisStart).toHaveBeenCalled();
    });

    it('does not touch body overflow on desktop while the dialog is open/closed cleanly', async () => {
        const wrapper = mountHub();
        await flushPromises();

        const reviewButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Review results'));
        await reviewButton!.trigger('click');
        await flushPromises();
        await nextTick();

        // The manual lock must not fire on desktop: reka sets 'hidden' itself,
        // and after close it restores the pre-dialog value ('') rather than a
        // value we raced ahead and wrote.
        const closeButton = Array.from(
            document.querySelectorAll('button'),
        ).find((b) => b.textContent?.trim() === 'Close');
        expect(closeButton).toBeTruthy();
        closeButton!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();
        await nextTick();
        await flushPromises();
        await nextTick();

        expect(document.body.style.overflow).toBe('');
    });
});
