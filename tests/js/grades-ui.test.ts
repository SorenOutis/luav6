import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import GradeDistributionChart from '@/components/GradeDistributionChart.vue';

const { sampleSubjects } = vi.hoisted(() => ({
    sampleSubjects: [
        {
            subject: 'Algebra',
            section: {
                id: 1,
                name: 'STEM-A',
                schoolLevel: 'college',
                schoolLevelLabel: 'College',
            },
            periods: [{ key: 'prelim', label: 'Prelim' }],
            periodGrades: [
                {
                    key: 'prelim',
                    label: 'Prelim',
                    grade: {
                        id: 1,
                        score: '90',
                        maxScore: '100',
                        percentage: 90,
                        remarks: null,
                        updatedAt: '2026-08-01',
                    },
                },
            ],
            semesterGrades: [],
            gradedPeriods: 1,
            totalPeriods: 3,
            isComplete: false,
            currentAverage: 90,
            semesterGrade: null,
        },
    ],
}));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(async () => ({
            data: { subjectGrades: sampleSubjects },
        })),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    // OnboardingTour reads the authenticated user's public_id for its
    // per-user localStorage scope.
    usePage: () => ({
        props: { auth: { user: { public_id: 'test-user' } } },
    }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup(_: unknown, { slots }: { slots: { default?: () => any } }) {
            return () => h('div', slots.default?.());
        },
    }),
}));

const Grades = (await import('@/pages/Grades.vue')).default;

describe('grades student shell', () => {
    it('uses the shared student page tokens', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Grades.vue'),
            'utf8',
        );
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );

        expect(page).toContain('student-ui');
        expect(page).toContain('dash-btn');
        expect(page).toContain('min-h-11');
        expect(page).toContain('Awaiting grades');
        expect(page).toContain('Current / Final');
        expect(page).toContain('prefers-reduced-motion');
        expect(page).not.toContain('tracking-wider');
        expect(css).toContain('.student-ui');
        expect(css).toContain('env(safe-area-inset-left)');
    });

    it('renders a readable header and 44px export on mobile', async () => {
        const wrapper = mount(Grades, {
            props: { subjectGrades: sampleSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.get('h1').text()).toBe('Grades');
        expect(wrapper.text()).toContain('Overall average');
        expect(wrapper.text()).toContain('Algebra');
        expect(wrapper.text()).toContain('Current average');
        expect(wrapper.text()).toContain('simple mean of available periods');
        expect(wrapper.html()).toContain('grid-cols-2');
        expect(wrapper.html()).toContain('md:hidden');
        expect(wrapper.html()).toContain('min-h-11');

        const exportBtn = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Export PDF'));
        expect(exportBtn?.classes()).toContain('dash-btn');
        expect(wrapper.html()).not.toContain('tracking-wider');
    });

    it('shows awaiting grades instead of treating pending work as zero', async () => {
        const pendingSubjects = sampleSubjects.map((subject) => ({
            ...subject,
            periodGrades: subject.periodGrades.map((period) => ({
                ...period,
                grade: null,
            })),
            gradedPeriods: 0,
            isComplete: false,
            currentAverage: null,
            semesterGrade: null,
        }));

        vi.mocked(axios.get).mockResolvedValueOnce({
            data: { subjectGrades: pendingSubjects },
        });

        const wrapper = mount(Grades, {
            props: { subjectGrades: pendingSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        const averageCard = wrapper
            .findAll('[data-slot="card"]')
            .find((card) => card.text().includes('Overall average'));

        expect(averageCard?.text()).toContain('Awaiting grades');
        expect(averageCard?.text()).not.toContain('Overall average0');
    });

    it('uses system status colors in the distribution chart', () => {
        const wrapper = mount(GradeDistributionChart, {
            props: {
                total: 4,
                segments: [
                    {
                        label: 'Excellent (≥85)',
                        count: 2,
                        color: '#34C759',
                        textColor: 'text-[#34C759]',
                    },
                    {
                        label: 'Needs Improvement (<60)',
                        count: 2,
                        color: '#FF3B30',
                        textColor: 'text-[#FF3B30]',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Excellent (≥85)');
        expect(wrapper.html()).toContain('#34C759');
        expect(wrapper.html()).toContain('#FF3B30');
        expect(wrapper.html()).not.toContain('text-[10px]');
        expect(wrapper.html()).toContain('font-semibold');
    });

    it('uses a compact summary strip and per-subject mobile cards', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Grades.vue'),
            'utf8',
        );

        // Compact strip shared by mobile + desktop.
        expect(page).toContain('grades-overview-strip');
        expect(page).toContain('Overall average');
        expect(page).toContain('With a final grade');

        // Per-subject accordion (one tap per subject, no per-period toggles).
        expect(page).toContain('grades-subject-card');
        expect(page).toContain('toggleSubject');
        expect(page).toContain('isSubjectExpanded');
        expect(page).toContain('toggleAllSubjects');
        expect(page).not.toContain('togglePeriod');
        expect(page).not.toContain('mobilePeriodKey');
        expect(page).not.toContain('periodMobileHeight');
    });

    it('expands and collapses a whole subject with one tap', async () => {
        const wrapper = mount(Grades, {
            props: { subjectGrades: sampleSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        const subjectToggle = wrapper
            .findAll('button')
            .find((button) =>
                (button.attributes('aria-label') ?? '').includes(
                    'Algebra grades',
                ),
            );

        expect(subjectToggle?.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Prelim');

        await subjectToggle?.trigger('click');

        expect(subjectToggle?.attributes('aria-expanded')).toBe('false');
    });

    it('renders a compact distribution chart inside the strip', () => {
        const wrapper = mount(GradeDistributionChart, {
            props: {
                total: 2,
                compact: true,
                segments: [
                    {
                        label: 'Excellent (≥85)',
                        count: 1,
                        color: '#34C759',
                        textColor: 'text-[#34C759]',
                    },
                    {
                        label: 'Good (70-84)',
                        count: 1,
                        color: '#EA580C',
                        textColor: 'text-orange-700',
                    },
                ],
            },
        });

        expect(wrapper.html()).toContain('h-12 w-12');
        expect(wrapper.html()).toContain('font-semibold');
        expect(wrapper.html()).not.toContain('text-[10px]');
    });

    it('shows period grades as integer scores with no extra labels', async () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Grades.vue'),
            'utf8',
        );

        expect(page).not.toContain('>Percentage<');
        expect(page).not.toContain('>Graded<');
        expect(page).not.toContain('>Updated<');

        const floatSubjects = sampleSubjects.map((subject) => ({
            ...subject,
            periodGrades: subject.periodGrades.map((period) => ({
                ...period,
                grade: period.grade
                    ? {
                          ...period.grade,
                          score: '92.50',
                          maxScore: '100.00',
                      }
                    : null,
            })),
        }));

        vi.mocked(axios.get).mockResolvedValueOnce({
            data: { subjectGrades: floatSubjects },
        });

        const wrapper = mount(Grades, {
            props: { subjectGrades: floatSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.text()).not.toContain('Percentage');
        expect(wrapper.text()).not.toContain('Graded');
        expect(wrapper.text()).not.toContain('Updated');
        // Earned score only: no "/ max" part, integers not floats.
        expect(wrapper.html()).toContain('92.5');
        expect(wrapper.html()).not.toContain('92.50');
        expect(wrapper.html()).not.toContain('100.00');

        // No "/ max" part in period cells (mobile or desktop).
        const periodCells = wrapper
            .findAll('.grades-desktop-layout table tbody td')
            .filter(
                (cell) => !(cell.attributes('class') ?? '').includes('sticky'),
            );
        expect(periodCells.length).toBeGreaterThan(0);
        for (const cell of periodCells) {
            expect(cell.text()).not.toContain('/');
        }
    });

    it('aligns graded scores and pending states in desktop columns', async () => {
        const twoPeriodSubjects = sampleSubjects.map((subject) => ({
            ...subject,
            periods: [
                { key: 'prelim', label: 'Prelim' },
                { key: 'midterm', label: 'Midterm' },
            ],
            periodGrades: [
                ...subject.periodGrades,
                { key: 'midterm', label: 'Midterm', grade: null },
            ],
        }));

        vi.mocked(axios.get).mockResolvedValueOnce({
            data: { subjectGrades: twoPeriodSubjects },
        });

        const wrapper = mount(Grades, {
            props: { subjectGrades: twoPeriodSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        // Period cells only (excludes the sticky Current/Final column).
        const periodCells = wrapper
            .findAll('.grades-desktop-layout table tbody td')
            .filter(
                (cell) => !(cell.attributes('class') ?? '').includes('sticky'),
            );
        expect(periodCells).toHaveLength(2);

        // Graded score and Pending share the same centered box layout.
        for (const cell of periodCells) {
            expect(cell.html()).toContain('min-h-11');
            expect(cell.html()).toContain('justify-center');
        }
        expect(periodCells[0].text()).toContain('90');
        expect(periodCells[1].text()).toContain('Pending');
    });

    it('hides the summary strip on phones but keeps it for desktop/export', async () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Grades.vue'),
            'utf8',
        );
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );

        const wrapper = mount(Grades, {
            props: { subjectGrades: sampleSubjects as any },
            global: {
                stubs: {
                    Head: { render: () => null },
                    AppLayout: {
                        setup(_: unknown, { slots }: any) {
                            return () => h('div', slots.default?.());
                        },
                    },
                },
            },
        });
        await flushPromises();

        // Desktop hook (also hides the strip on phones/touch tablets via CSS).
        const strip = wrapper.find('[data-tour="grades-overview"]');
        expect(strip.exists()).toBe(true);
        expect(strip.classes()).toContain('grades-desktop-overview');
        expect(strip.classes()).toContain('grades-overview-strip');
        expect(css).toContain('.grades-desktop-overview');

        // Content still rendered for desktop and PDF export.
        expect(strip.text()).toContain('Overall average');
        expect(page).toContain('.grades-export-mode .grades-overview-strip');

        // Averaging explainer is desktop-only; phones keep just Last updated.
        const explainer = wrapper
            .findAll('p')
            .find((p) => p.text().includes('simple mean'));
        expect(explainer?.classes()).toContain('hidden');
        expect(explainer?.classes()).toContain('md:block');
        expect(wrapper.text()).toContain('Last updated');
    });
});
