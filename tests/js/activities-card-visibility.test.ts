import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';
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
    router: { reload: vi.fn(), visit: vi.fn() },
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

const mkExam = (id: number, season: string, section: string) => ({
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
        {
            id: id * 10 + 2,
            title: 'Part II',
            instructions: null,
            type: 'mc',
            questions: [],
            points: 10,
        },
    ],
    submitted_parts_count: 0,
    total_parts: 2,
    is_locked: false,
    has_submissions: false,
    results_available: false,
    submissions: [],
    section_name: section,
    season_name: season,
});

const hubStats = (n: number) => ({
    exams: { total: n, pending: n, completed: 0 },
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

describe('activities hub — exam card visibility', () => {
    it('renders one card per exam across multiple seasons', async () => {
        const groups = [
            {
                seasonName: 'Season 1',
                exams: [
                    mkExam(1, 'Season 1', 'BSIT 1-A'),
                    mkExam(2, 'Season 1', 'BSIT 1-A'),
                ],
            },
            {
                seasonName: 'Season 2',
                exams: [mkExam(3, 'Season 2', 'BSIT 1-B')],
            },
        ];
        const wrapper = mount(Activities, {
            props: {
                examsBySeason: groups as any,
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [{ key: 'all', label: 'All sections', count: 3 }],
                hubStats: hubStats(3),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(3);
    });

    it('keeps every card after the 10s poll replaces examsBySeason', async () => {
        const groups = [
            {
                seasonName: 'Season 1',
                exams: [
                    mkExam(1, 'Season 1', 'BSIT 1-A'),
                    mkExam(2, 'Season 1', 'BSIT 1-A'),
                ],
            },
        ];
        const wrapper = mount(Activities, {
            props: {
                examsBySeason: groups as any,
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [{ key: 'all', label: 'All sections', count: 2 }],
                hubStats: hubStats(2),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(2);

        // Inertia hands back a brand-new array of brand-new objects.
        await wrapper.setProps({
            examsBySeason: [
                {
                    seasonName: 'Season 1',
                    exams: [
                        mkExam(1, 'Season 1', 'BSIT 1-A'),
                        mkExam(2, 'Season 1', 'BSIT 1-A'),
                    ],
                },
            ] as any,
            hubStats: hubStats(2),
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(2);
    });

    it('keeps every card when the poll returns seasons in a different order', async () => {
        const wrapper = mount(Activities, {
            props: {
                examsBySeason: [
                    {
                        seasonName: 'Season 1',
                        exams: [mkExam(1, 'Season 1', 'BSIT 1-A')],
                    },
                    {
                        seasonName: 'Season 2',
                        exams: [mkExam(2, 'Season 2', 'BSIT 1-B')],
                    },
                ] as any,
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [{ key: 'all', label: 'All sections', count: 2 }],
                hubStats: hubStats(2),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(2);

        await wrapper.setProps({
            examsBySeason: [
                {
                    seasonName: 'Season 2',
                    exams: [mkExam(2, 'Season 2', 'BSIT 1-B')],
                },
                {
                    seasonName: 'Season 1',
                    exams: [mkExam(1, 'Season 1', 'BSIT 1-A')],
                },
            ] as any,
            hubStats: hubStats(2),
        });
        await flushPromises();
        const cards = wrapper.findAll('.exam-card');
        expect(cards).toHaveLength(2);
        expect(cards.map((c) => c.text())).toEqual(
            expect.arrayContaining([
                expect.stringContaining('Exam 1'),
                expect.stringContaining('Exam 2'),
            ]),
        );
    });

    it('keeps every card when a later poll drops a season group', async () => {
        const wrapper = mount(Activities, {
            props: {
                examsBySeason: [
                    {
                        seasonName: 'Season 1',
                        exams: [mkExam(1, 'Season 1', 'BSIT 1-A')],
                    },
                    {
                        seasonName: 'Season 2',
                        exams: [mkExam(2, 'Season 2', 'BSIT 1-B')],
                    },
                ] as any,
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [{ key: 'all', label: 'All sections', count: 2 }],
                hubStats: hubStats(2),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(2);

        // Season 2's only exam was deleted / unpublished server-side.
        await wrapper.setProps({
            examsBySeason: [
                {
                    seasonName: 'Season 1',
                    exams: [mkExam(1, 'Season 1', 'BSIT 1-A')],
                },
            ] as any,
            hubStats: hubStats(1),
        });
        await flushPromises();
        const cards = wrapper.findAll('.exam-card');
        expect(cards).toHaveLength(1);
        expect(cards[0].text()).toContain('Exam 1');
    });

    it('tags every card with the status accent the stylesheet keys on', async () => {
        const done = {
            ...mkExam(1, 'Season 1', 'BSIT 1-A'),
            status: 'closed',
            submitted_parts_count: 2,
            is_locked: true,
            has_submissions: true,
            results_available: true,
            submissions: [
                { id: 9, exam_part_id: 11, status: 'graded', score: '8.00' },
            ],
        };
        const overdue = {
            ...mkExam(2, 'Season 1', 'BSIT 1-A'),
            exam_date: '2020-01-01 12:00:00',
            exam_date_iso: '2020-01-01T12:00:00+08:00',
        };
        const open = mkExam(3, 'Season 1', 'BSIT 1-A');

        const wrapper = mount(Activities, {
            props: {
                examsBySeason: [
                    {
                        seasonName: 'Season 1',
                        exams: [done, overdue, open] as any,
                    },
                ],
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [{ key: 'all', label: 'All sections', count: 3 }],
                hubStats: hubStats(3),
            },
            global: globalStubs,
        });
        await flushPromises();

        const cards = wrapper.findAll('.exam-card');
        expect(cards).toHaveLength(3);
        expect(cards.map((c) => c.attributes('data-accent'))).toEqual([
            'done',
            'overdue',
            'open',
        ]);

        // The accent has to be a data attribute, not an inline style: `Motion`
        // declares its own `style` prop and would swallow it.
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );
        expect(css).toContain(
            ".exam-theme-page .exam-card[data-accent='done']",
        );
        expect(css).toContain(
            ".exam-theme-page .exam-card[data-accent='overdue']",
        );
    });

    it('keeps the section tabs in sync with the poll', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Activities/Index.vue'),
            'utf8',
        );

        // `sectionTabs` describes the whole catalogue; if the poll skips it the
        // tab counts go stale the moment an exam is published or closed.
        expect(page).toContain(
            "only: ['examsBySeason', 'hubStats', 'sectionTabs']",
        );
    });

    it('derives the hub totals and section tabs from every visible exam', () => {
        const controller = readFileSync(
            join(
                process.cwd(),
                'app/Http/Controllers/ActivityHubController.php',
            ),
            'utf8',
        );

        // Regression: both were folded out of `$examPage['data']`, which is a
        // 24-row cursor page — so the counters froze at 24 and any section
        // living past the first page never got a tab.
        expect(controller).toContain('$summary = $this->hubSummary($user);');
        expect(controller).not.toContain(
            "\$allExams = collect(\$examPage['data'])",
        );
        expect(controller).toContain(
            'private function hubSummary(User $user): array',
        );
        expect(controller).toContain(
            'private function visibleExams(User $user)',
        );
    });

    it('filters by section without dropping whole season groups', async () => {
        const wrapper = mount(Activities, {
            props: {
                examsBySeason: [
                    {
                        seasonName: 'Season 1',
                        exams: [
                            mkExam(1, 'Season 1', 'BSIT 1-A'),
                            mkExam(2, 'Season 1', 'BSIT 1-B'),
                        ],
                    },
                ] as any,
                examPagination: { hasMore: false, nextCursor: null },
                sectionTabs: [
                    { key: 'all', label: 'All sections', count: 2 },
                    { key: 'BSIT 1-A', label: 'BSIT 1-A', count: 1 },
                    { key: 'BSIT 1-B', label: 'BSIT 1-B', count: 1 },
                ],
                hubStats: hubStats(2),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(2);

        const tabs = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('BSIT 1-B'));
        expect(tabs.length).toBeGreaterThan(0);
        await tabs[0].trigger('click');
        await nextTick();
        const cards = wrapper.findAll('.exam-card');
        expect(cards).toHaveLength(1);
        expect(cards[0].text()).toContain('Exam 2');
    });

    it('auto-loads later pages when a section tab has no cards in the loaded pages yet', async () => {
        // The tab badges come from the whole catalogue, so BSIT 1-B counts an
        // exam that lives past the first (24-row) page.
        const mockGet = vi.mocked(axios.get);
        mockGet.mockResolvedValueOnce({
            data: {
                data: [
                    {
                        seasonName: 'Season 2',
                        exams: [mkExam(2, 'Season 2', 'BSIT 1-B')],
                    },
                ],
                meta: { hasMore: false, nextCursor: null },
            },
        });

        const wrapper = mount(Activities, {
            props: {
                examsBySeason: [
                    {
                        seasonName: 'Season 1',
                        exams: [mkExam(1, 'Season 1', 'BSIT 1-A')],
                    },
                ] as any,
                examPagination: { hasMore: true, nextCursor: 'cursor-2' },
                sectionTabs: [
                    { key: 'all', label: 'All sections', count: 2 },
                    { key: 'BSIT 1-A', label: 'BSIT 1-A', count: 1 },
                    { key: 'BSIT 1-B', label: 'BSIT 1-B', count: 1 },
                ],
                hubStats: hubStats(2),
            },
            global: globalStubs,
        });
        await flushPromises();
        expect(wrapper.findAll('.exam-card')).toHaveLength(1);

        const tabs = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('BSIT 1-B'));
        await tabs[0].trigger('click');
        await flushPromises();
        await flushPromises();

        // The hub pulled the next page instead of resting on "No exams found".
        expect(mockGet).toHaveBeenCalledWith('/api/activities', {
            params: { cursor: 'cursor-2' },
        });
        const cards = wrapper.findAll('.exam-card');
        expect(cards).toHaveLength(1);
        expect(cards[0].text()).toContain('Exam 2');
    });
});
