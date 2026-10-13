import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn() },
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
        post: vi.fn(),
        delete: vi.fn(),
    },
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
    usePage: () => ({
        props: { auth: { user: { id: 1, public_id: 'test-user' } } },
    }),
    useForm: (initialValues: any) => ({
        ...initialValues,
        processing: false,
        errors: {},
        reset: vi.fn(),
        post: vi.fn(),
    }),
}));

vi.mock('@motionone/vue', () => ({
    Motion: defineComponent({
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
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

vi.mock('@laravel/echo-vue', () => ({
    useEcho: vi.fn(),
}));

const Activities = (await import('@/pages/Activities/Index.vue')).default;
const AssignmentsTab = (
    await import('@/pages/Activities/Partials/AssignmentsTab.vue')
).default;

const stubs = {
    Head: { render: () => null },
    AppLayout: {
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    },
    OnboardingTour: { render: () => null },
    ResponsiveModal: { render: () => null },
    AssignmentsTab: {
        props: ['assignments'],
        template:
            '<div data-test="stubbed-assignments-tab">Assignments Tab Content</div>',
    },
    ActivityRecordSheet: {
        props: ['open', 'groups'],
        template:
            '<div data-test="stubbed-record-sheet" :data-open="String(open)">Record Sheet Content</div>',
    },
};

const mountHub = (propsOverrides: Record<string, any> = {}) =>
    mount(Activities, {
        props: {
            examsBySeason: [
                {
                    seasonName: 'Season 1',
                    exams: [
                        {
                            id: 1,
                            title: 'Exam 1',
                            description: 'Description 1',
                            exam_date: '2026-09-10',
                            duration_minutes: 45,
                            status: 'published',
                            url: null,
                            parts: [],
                            submitted_parts_count: 0,
                            total_parts: 1,
                            is_locked: false,
                        },
                    ],
                },
            ] as any,
            examPagination: { hasMore: false, nextCursor: null },
            sectionTabs: [{ key: 'all', label: 'All sections', count: 1 }],
            hubStats: { exams: { total: 1, pending: 1, completed: 0 } },
            assignments: [
                {
                    id: 10,
                    title: 'Homework 1',
                    description: 'Read chapter 1',
                    status: 'published',
                    submission: null,
                },
            ],
            activityScores: [
                {
                    seasonName: 'Season 1',
                    exams: [
                        {
                            id: 1,
                            title: 'Exam 1',
                            section_name: 'BSIT 1-A',
                            score: 90,
                            submitted: true,
                            state: 'completed',
                        },
                    ],
                },
            ],
            activityRecordEnabled: true,
            ...propsOverrides,
        },
        global: { stubs },
    });

describe('Activity Hub workspace tabs', () => {
    beforeEach(() => {
        window.history.replaceState({}, '', '/activities');
    });

    afterEach(() => {
        window.history.replaceState({}, '', '/activities');
    });

    it('renders workspace tabs for Exams and Assignments', async () => {
        const wrapper = mountHub();
        await flushPromises();

        const tabs = wrapper.findAll('.dash-btn');
        const tabTexts = tabs.map((t) => t.text());

        expect(tabTexts.some((txt) => txt.includes('Exams'))).toBe(true);
        expect(tabTexts.some((txt) => txt.includes('Assignments'))).toBe(true);
        expect(tabTexts.some((txt) => txt.includes('Activity Record'))).toBe(
            false,
        );
    });

    it('defaults to the exams tab and displays exam cards', async () => {
        const wrapper = mountHub();
        await flushPromises();

        expect(wrapper.find('.exam-card').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="stubbed-assignments-tab"]').exists(),
        ).toBe(false);
    });

    it('switches to the assignments tab when clicked and updates url query', async () => {
        const wrapper = mountHub();
        await flushPromises();

        const assignmentsTabBtn = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Assignments'));
        expect(assignmentsTabBtn).toBeTruthy();

        await assignmentsTabBtn!.trigger('click');
        await nextTick();
        await flushPromises();

        expect(
            wrapper.find('[data-test="stubbed-assignments-tab"]').exists(),
        ).toBe(true);
        expect(window.location.search).toContain('tab=assignments');
    });

    it('opens the activity record sheet when header button is clicked', async () => {
        const wrapper = mountHub();
        await flushPromises();

        const recordBtn = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Activity Record'));
        expect(recordBtn).toBeTruthy();

        expect(
            wrapper
                .find('[data-test="stubbed-record-sheet"]')
                .attributes('data-open'),
        ).toBe('false');

        await recordBtn!.trigger('click');
        await nextTick();
        await flushPromises();

        expect(
            wrapper
                .find('[data-test="stubbed-record-sheet"]')
                .attributes('data-open'),
        ).toBe('true');
    });

    it('removes tab query parameter when switching back to exams', async () => {
        window.history.replaceState({}, '', '/activities?tab=assignments');
        const wrapper = mountHub({ initialTab: 'assignments' });
        await flushPromises();

        expect(
            wrapper.find('[data-test="stubbed-assignments-tab"]').exists(),
        ).toBe(true);

        const examsTabBtn = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Exams'));
        expect(examsTabBtn).toBeTruthy();

        await examsTabBtn!.trigger('click');
        await nextTick();
        await flushPromises();

        expect(wrapper.find('.exam-card').exists()).toBe(true);
        expect(window.location.search).not.toContain('tab=');
    });

    it('hides Activity Record header button when activityRecordEnabled is false', async () => {
        const wrapper = mountHub({ activityRecordEnabled: false });
        await flushPromises();

        const recordBtns = wrapper
            .findAll('button')
            .filter(
                (b) =>
                    b.text().includes('Activity Record') ||
                    b.text() === 'Record',
            );

        expect(recordBtns.length).toBe(0);
    });
});

describe('Activity Hub unified toolbars', () => {
    it('AssignmentsTab has unified toolbar with no duplicate overview card', () => {
        const wrapper = mount(AssignmentsTab, {
            props: {
                assignments: [
                    {
                        id: 1,
                        title: 'HW 1',
                        description: 'Desc',
                        due_date: '2026-10-15T00:00:00Z',
                        course: { id: 1, name: 'Math' },
                        sections: [{ id: 1, name: 'BSIT 1-A' }],
                        submission: null,
                    },
                ] as any,
            } as any,
        });

        // Overview stats section is removed
        expect(
            wrapper.find('section[aria-label="Assignment overview"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Awaiting submission');

        // Unified search bar exists
        const searchInput = wrapper.find('.activities-mobile-search input');
        expect(searchInput.exists()).toBe(true);
        expect(searchInput.attributes('placeholder')).toBe(
            'Search assignments...',
        );

        // Contextual filters exist
        const filterButtons = wrapper.findAll('button').map((b) => b.text());
        expect(filterButtons.some((t) => t.includes('All'))).toBe(true);
        expect(filterButtons.some((t) => t.includes('Pending'))).toBe(true);
        expect(filterButtons.some((t) => t.includes('Submitted'))).toBe(true);
        expect(filterButtons.some((t) => t.includes('Graded'))).toBe(true);
        expect(
            wrapper.find('select[aria-label="Filter by month"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('select[aria-label="Sort assignments"]').exists(),
        ).toBe(true);
    });
});
