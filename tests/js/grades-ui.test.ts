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
});
