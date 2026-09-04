import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

vi.mock('axios', () => ({
    default: { get: vi.fn() },
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
        on: vi.fn(() => vi.fn()),
    },
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
    usePage: () => ({
        props: { auth: { user: { id: 1, public_id: 'test-user' } } },
    }),
}));

vi.mock('@/composables/useLenis', () => ({
    getLenis: vi.fn(() => ({ stop: vi.fn(), start: vi.fn() })),
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

const Activities = (await import('@/pages/Activities/Index.vue')).default;

const mkExam = (id: number, season = 'Season 1', section = 'BSIT 1-A') => ({
    id,
    title: `Exam ${id}`,
    description: `Description ${id}`,
    exam_date: '2026-09-10 12:00:00',
    exam_date_iso: '2026-09-10T12:00:00+08:00',
    duration_minutes: 45,
    status: 'published',
    url: null,
    parts: [
        {
            id: id * 10 + 1,
            title: 'Part I',
            instructions: null,
            type: 'mc',
            questions: [],
            points: 10,
        },
    ],
    submitted_parts_count: 0,
    total_parts: 1,
    is_locked: false,
    has_submissions: false,
    results_available: false,
    submissions: [],
    starts_at_iso: null,
    ends_at_iso: null,
    is_upcoming: false,
    is_open_now: true,
    has_ended: false,
    section_name: section,
    season_name: season,
});

const globalStubs = {
    stubs: {
        Head: { render: () => null },
        AppLayout: {
            setup(_: any, { slots }: any) {
                return () => h('div', slots.default?.());
            },
        },
        OnboardingTour: { render: () => null },
        ResponsiveModal: { render: () => null },
    },
};

const mountHub = (
    exams: unknown[],
    pagination: { hasMore: boolean; nextCursor: string | null } = {
        hasMore: false,
        nextCursor: null,
    },
) =>
    mount(Activities, {
        props: {
            examsBySeason: [{ seasonName: 'Season 1', exams }] as any,
            examPagination: pagination as any,
            sectionTabs: [
                { key: 'all', label: 'All sections', count: exams.length },
            ],
            hubStats: {
                exams: {
                    total: exams.length,
                    pending: exams.length,
                    completed: 0,
                },
            },
        },
        global: globalStubs,
    });

// jsdom ships no scrollIntoView, and the deep link scrolls the target card
// into view — so the call has to be observable instead of a TypeError. Typed
// to match the DOM signature it stands in for, or vue-tsc rejects assigning it
// to Element.prototype.
const scrollIntoView = vi.fn<(arg?: boolean | ScrollIntoViewOptions) => void>();

const HIGHLIGHT_CLASS = 'outline-[#D97757]';

beforeEach(() => {
    scrollIntoView.mockClear();
    Element.prototype.scrollIntoView = scrollIntoView;
    vi.mocked(axios.get).mockReset();
    window.history.replaceState({}, '', '/activities');
});

afterEach(() => {
    vi.useRealTimers();
    window.history.replaceState({}, '', '/activities');
});

describe('activities hub — calendar deep link', () => {
    it('points calendar exam events at the hub instead of the exam paper', () => {
        const service = readFileSync(
            join(process.cwd(), 'app/Services/CalendarEventService.php'),
            'utf8',
        );

        // /exams/{id} renders every part before the student has started, so a
        // calendar click must land on the hub card instead.
        expect(service).toContain('"/activities?exam={$exam->id}"');
        expect(service).not.toContain('"/exams/{$exam->id}"');
    });

    it('gives every card a stable id the deep link can target', async () => {
        const wrapper = mountHub([mkExam(1), mkExam(2)]);
        await flushPromises();

        expect(wrapper.find('#exam-card-1').exists()).toBe(true);
        expect(wrapper.find('#exam-card-2').exists()).toBe(true);
    });

    it('scrolls to and highlights the exam named in ?exam=', async () => {
        window.history.replaceState({}, '', '/activities?exam=2');

        const wrapper = mountHub([mkExam(1), mkExam(2), mkExam(3)]);
        await flushPromises();

        const target = wrapper.find('#exam-card-2');
        expect(scrollIntoView).toHaveBeenCalledTimes(1);
        expect(scrollIntoView.mock.instances[0]).toBe(target.element);
        expect(target.classes()).toContain(HIGHLIGHT_CLASS);

        // Only the linked card is marked — the highlight is a pointer, not a
        // new visual state for the whole grid.
        expect(wrapper.find('#exam-card-1').classes()).not.toContain(
            HIGHLIGHT_CLASS,
        );
        expect(wrapper.find('#exam-card-3').classes()).not.toContain(
            HIGHLIGHT_CLASS,
        );
    });

    it('clears the highlight again once the student has seen it', async () => {
        vi.useFakeTimers();
        window.history.replaceState({}, '', '/activities?exam=1');

        const wrapper = mountHub([mkExam(1), mkExam(2)]);
        // Fake timers also fake the setImmediate flushPromises relies on, so
        // the queue is advanced explicitly instead.
        await vi.advanceTimersByTimeAsync(0);
        expect(wrapper.find('#exam-card-1').classes()).toContain(
            HIGHLIGHT_CLASS,
        );

        await vi.advanceTimersByTimeAsync(2500);
        await nextTick();
        expect(wrapper.find('#exam-card-1').classes()).not.toContain(
            HIGHLIGHT_CLASS,
        );
    });

    it('pulls later pages until the linked exam is on the grid', async () => {
        window.history.replaceState({}, '', '/activities?exam=5');
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: [
                    { seasonName: 'Season 2', exams: [mkExam(5, 'Season 2')] },
                ],
                meta: { hasMore: false, nextCursor: null },
            },
        });

        const wrapper = mountHub([mkExam(1)], {
            hasMore: true,
            nextCursor: 'cursor-2',
        });
        await flushPromises();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/activities', {
            params: { cursor: 'cursor-2' },
        });

        const target = wrapper.find('#exam-card-5');
        expect(target.exists()).toBe(true);
        expect(target.classes()).toContain(HIGHLIGHT_CLASS);
        expect(scrollIntoView).toHaveBeenCalledTimes(1);
    });

    it('stops paging when the linked exam never shows up', async () => {
        window.history.replaceState({}, '', '/activities?exam=999');

        // Every page answers "there is more" without ever containing exam 999:
        // one calendar click must not turn into an endless paging loop.
        let page = 0;
        vi.mocked(axios.get).mockImplementation(async () => {
            page += 1;
            return {
                data: {
                    data: [
                        {
                            seasonName: 'Season 1',
                            exams: [mkExam(100 + page)],
                        },
                    ],
                    meta: { hasMore: true, nextCursor: `cursor-${page + 1}` },
                },
            } as any;
        });

        const wrapper = mountHub([mkExam(1)], {
            hasMore: true,
            nextCursor: 'cursor-2',
        });
        await flushPromises();
        await flushPromises();

        expect(vi.mocked(axios.get).mock.calls.length).toBeLessThanOrEqual(8);
        expect(scrollIntoView).not.toHaveBeenCalled();
        expect(
            wrapper
                .findAll('.exam-card')
                .some((card) => card.classes().includes(HIGHLIGHT_CLASS)),
        ).toBe(false);
    });

    it('leaves the grid alone when there is no ?exam= param', async () => {
        const wrapper = mountHub([mkExam(1), mkExam(2)]);
        await flushPromises();

        expect(axios.get).not.toHaveBeenCalled();
        expect(scrollIntoView).not.toHaveBeenCalled();
        expect(
            wrapper
                .findAll('.exam-card')
                .some((card) => card.classes().includes(HIGHLIGHT_CLASS)),
        ).toBe(false);
    });

    it('follows a deep link that arrives while the hub is already open', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountHub([mkExam(1), mkExam(2)]);
        await flushPromises();

        const onNavigate = vi.mocked(router.on).mock.calls.at(-1)?.[1];
        expect(onNavigate).toBeTypeOf('function');

        // Inertia reuses the page component when the hub links to itself, so
        // the param has to be picked up from the navigate event, not onMounted.
        onNavigate?.({
            detail: { page: { url: '/activities?exam=2' } },
        } as any);
        await flushPromises();

        expect(wrapper.find('#exam-card-2').classes()).toContain(
            HIGHLIGHT_CLASS,
        );
        expect(scrollIntoView).toHaveBeenCalledTimes(1);
    });
});
