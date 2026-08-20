import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

const sampleAssignments = [
    {
        id: 1,
        title: 'Calculus Problem Set 1',
        description: 'Complete exercises 1 through 15 on page 42.',
        due_date: '2026-09-01T23:59:59Z',
        course: { id: 101, name: 'Calculus I' },
        sections: [{ id: 1, name: 'BSIT 1-A' }],
        submission: null,
    },
    {
        id: 2,
        title: 'Physics Lab Report',
        description:
            'Write up the pendulum experiment results and error analysis.',
        due_date: '2026-08-15T23:59:59Z',
        course: { id: 102, name: 'Physics 101' },
        sections: [
            { id: 1, name: 'BSIT 1-A' },
            { id: 2, name: 'BSIT 1-B' },
        ],
        submission: {
            submitted: true,
            status: 'Submitted',
            grade: null,
            file_path: 'assignments/1/lab_report.pdf',
            submitted_at: '2026-08-14T14:30:00Z',
        },
    },
    {
        id: 3,
        title: 'Literature Essay',
        description: 'Analyze the theme of isolation in Frankenstein.',
        due_date: '2026-08-10T23:59:59Z',
        course: { id: 103, name: 'World Literature' },
        sections: [{ id: 2, name: 'BSIT 1-B' }],
        submission: {
            submitted: true,
            status: 'Graded',
            grade: '95',
            file_path: 'assignments/1/essay.docx',
            submitted_at: '2026-08-09T18:00:00Z',
        },
    },
];

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    usePage: () => ({
        props: { auth: { user: { public_id: 'test-user' } } },
    }),
    useForm: (initialValues: any) => ({
        ...initialValues,
        processing: false,
        errors: {},
        reset: vi.fn(),
        post: vi.fn(),
    }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup(_: unknown, { slots }: { slots: { default?: () => any } }) {
            return () => h('div', slots.default?.());
        },
    }),
}));

const Assignments = (await import('@/pages/Assignments.vue')).default;

describe('assignments student shell and UI revamp', () => {
    it('uses shared student page design tokens and removes all tactical jargon', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Assignments.vue'),
            'utf8',
        );

        // Required design tokens
        expect(page).toContain('student-ui');
        expect(page).toContain('dash-title');
        expect(page).toContain('dash-label');
        expect(page).toContain('dash-metric');
        expect(page).toContain('dash-btn');
        expect(page).toContain('surface-card');

        // Stripped tactical / military jargon
        expect(page).not.toContain('Mission_Briefings');
        expect(page).not.toContain('SUBMIT_INTEL');
        expect(page).not.toContain('RANK:VANGUARD');
        expect(page).not.toContain('ACTIVE_OBJECTIVES');
        expect(page).not.toContain('IMMEDIATE_PRIORITY');
        expect(page).not.toContain('COMPLETED_MISSIONS');
        expect(page).not.toContain('TOP_1%_OF_BATTALION');
        expect(page).not.toContain('>_TRANSMISSION');
        expect(page).not.toContain('>_DEADLINE');
        expect(page).not.toContain('>_SECURE_DATA');
        expect(page).not.toContain('Transmission Protocol');
        expect(page).not.toContain('Discard Intelligence');
        expect(page).not.toContain('Confirm Transmission');
        expect(page).not.toContain('TRANSMITTING...');
        expect(page).not.toContain('animate-scan-horizontal');
    });

    it('renders overview stats cards, filter controls, and assignments', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: {
                        setup(_: unknown, { slots }: any) {
                            return () =>
                                h('div', [
                                    slots.header?.(),
                                    slots.default?.(),
                                    slots.footer?.(),
                                ]);
                        },
                    },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        // Title
        expect(wrapper.get('h1').text()).toBe('Assignments');

        // Stats
        expect(wrapper.text()).toContain('Pending');
        expect(wrapper.text()).toContain('Submitted');
        expect(wrapper.text()).toContain('Graded');
        expect(wrapper.text()).toContain('Completion');

        // Assignment cards
        expect(wrapper.text()).toContain('Calculus Problem Set 1');
        expect(wrapper.text()).toContain('Physics Lab Report');
        expect(wrapper.text()).toContain('Literature Essay');
        expect(wrapper.text()).toContain('Calculus I');
        expect(wrapper.text()).toContain('Physics 101');
        expect(wrapper.text()).toContain('World Literature');

        // Compact assignment cards — not stretched 2-column slabs
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Assignments.vue'),
            'utf8',
        );
        expect(page).toContain('xl:grid-cols-3');
        expect(page).toContain('items-start');
        expect(page).toContain('line-clamp-2');
        expect(page).not.toContain('max-h-[240px]');
        expect(page).not.toContain('lg:grid-cols-2');

        // Submit action button exists
        const submitBtns = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('Submit'));
        expect(submitBtns.length).toBeGreaterThan(0);
    });

    it('filters assignments by tab', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: { render: () => null },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        // Switch to Pending tab
        const pendingTab = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Pending'));
        expect(pendingTab).toBeDefined();
        await pendingTab?.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Calculus Problem Set 1');
        expect(wrapper.text()).not.toContain('Physics Lab Report');
        expect(wrapper.text()).not.toContain('Literature Essay');

        // Switch to Submitted tab
        const submittedTab = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Submitted'));
        expect(submittedTab).toBeDefined();
        await submittedTab?.trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Calculus Problem Set 1');
        expect(wrapper.text()).toContain('Physics Lab Report');
        expect(wrapper.text()).toContain('Literature Essay');
    });

    it('filters assignments by search query', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: { render: () => null },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        const searchInput = wrapper.find('input[type="search"]');
        expect(searchInput.exists()).toBe(true);

        await searchInput.setValue('Calculus');
        await flushPromises();

        expect(wrapper.text()).toContain('Calculus Problem Set 1');
        expect(wrapper.text()).not.toContain('Physics Lab Report');
        expect(wrapper.text()).not.toContain('Literature Essay');
    });

    it('does not render a page-level "Submit assignment" header button', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: { render: () => null },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        // The data-tour hook for the old free-submit button is gone.
        expect(
            wrapper.find('[data-tour="assignments-submit-btn"]').exists(),
        ).toBe(false);

        // The header-level "Submit assignment" CTA used to live next to the
        // page title. After the change, the only "Submit" text in the page
        // comes from per-card action buttons (and the upload modal footer).
        const headerSubmit = wrapper
            .findAll('button')
            .filter((b) => b.text().trim() === 'Submit assignment');
        expect(headerSubmit.length).toBe(0);
    });

    it('omits the General course fallback and keeps Resubmit beside View grade', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Assignments.vue'),
            'utf8',
        );

        expect(page).not.toContain("'General'");
        expect(page).toContain('v-if="assignment.course?.name"');

        const submittedBlock =
            /v-if="assignment.submission\?\.submitted"[\s\S]*?v-if="!assignment.submission\?\.submitted"/m.exec(
                page,
            );
        expect(submittedBlock).not.toBeNull();
        expect(submittedBlock![0]).toContain('View grade');
        expect(submittedBlock![0]).toContain('Resubmit');
        expect(submittedBlock![0]).toContain(
            'flex flex-wrap items-center gap-2',
        );
    });

    it('disables the resubmit button on graded assignments', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: { render: () => null },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        const resubmitButtons = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('Resubmit'));

        // Two cards have a submission: one graded, one pending review.
        expect(resubmitButtons.length).toBe(2);

        // Resubmit for a graded submission (Literature Essay) must be locked
        // and show an explanatory tooltip.
        const graded = resubmitButtons.find(
            (b) => b.text() === 'Resubmit' && b.attributes('title'),
        );
        expect(graded).toBeDefined();
        expect(graded!.attributes('disabled')).toBeDefined();
        expect(graded!.attributes('title')).toMatch(/graded/i);

        // Resubmit for a submitted-but-not-graded card stays interactive.
        const notGraded = resubmitButtons.find(
            (b) => !b.attributes('disabled'),
        );
        expect(notGraded).toBeDefined();
    });

    it('shows the graded status badge on graded cards without expanding details', async () => {
        const wrapper = mount(Assignments, {
            props: { assignments: sampleAssignments as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                    ResponsiveModal: { render: () => null },
                    OnboardingTour: { render: () => null },
                },
            },
        });
        await flushPromises();

        // The graded submission's status badge must be visible at the top of
        // its card even before the student taps "View grade". It should
        // include the numeric grade.
        expect(wrapper.text()).toContain('Graded · 95');
    });

    it('equalizes the height of stat overview cards on mobile', async () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Assignments.vue'),
            'utf8',
        );

        // The stats grid should stretch its items so cards sharing a row on
        // mobile are the same height — fixes the "blank space" gap below
        // the shorter card in a 2-col row.
        expect(page).toMatch(/grid grid-cols-2[^"]*items-stretch/);

        // Every stat card opts in to row-stretching via h-full.
        const statCardClassMatches =
            page.match(/class="surface-card[^"]*?h-full[^"]*?"/g) ?? [];
        expect(statCardClassMatches.length).toBeGreaterThanOrEqual(4);
    });

    it('does not show the Echo floating widget on the assignments page', () => {
        const widget = readFileSync(
            join(process.cwd(), 'resources/js/components/FloatingWidget.vue'),
            'utf8',
        );

        expect(widget).toContain("page.component === 'Dashboard'");
        expect(widget).not.toContain("component === 'Assignments'");
    });

    it('uses a same-shape icon for the sort select so it lines up with the other filters', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Assignments.vue'),
            'utf8',
        );

        // The previous ArrowUpDown icon was a visually different glyph from
        // the Calendar/Clock icons used by the other selects, which threw
        // off the right-edge alignment.
        expect(page).not.toContain('ArrowUpDown');
        // Grab the sort select's wrapper div, which contains both the
        // <select> and its trailing icon.
        const sortWrapper =
            /<div class="relative">\s*<select[^>]*v-model="sortBy"[\s\S]*?<\/div>/m.exec(
                page,
            );
        expect(sortWrapper).not.toBeNull();
        const sortBlock = sortWrapper![0];
        // The sort's icon must share the same positioning classes as the
        // Calendar/Clock icons on the other selects so the visual right-edge
        // matches.
        expect(sortBlock).toMatch(
            /absolute top-1\/2 right-3 h-3\.5 w-3\.5 -translate-y-1\/2/,
        );
    });
});
