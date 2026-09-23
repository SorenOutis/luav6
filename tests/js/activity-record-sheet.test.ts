import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import type {
    ActivityScoreItem,
    ScoreGroup,
} from '@/pages/Activities/Partials/ActivityRecordSheet.vue';
import ActivityRecordSheet from '@/pages/Activities/Partials/ActivityRecordSheet.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: {
                user: { name: 'Test Student', email: 'student@example.com' },
            },
        },
    }),
}));

const slotStub = defineComponent({
    setup(_, { slots }) {
        return () => h('div', slots.default?.());
    },
});

const activity = (
    overrides: Partial<ActivityScoreItem> = {},
): ActivityScoreItem => ({
    id: 1,
    title: 'Written quiz',
    category: 'written',
    activity_type: 'Written Work',
    term: 'Prelims',
    section_name: 'BSIT 1-A',
    score: 40,
    total_points: 50,
    percentage: 80,
    submitted: true,
    state: 'completed',
    ...overrides,
});

const task = (overrides: Partial<ActivityScoreItem> = {}) =>
    activity({
        id: 'task-1',
        title: 'Practical task',
        category: 'performance',
        activity_type: 'Performance Task',
        score: 90,
        total_points: 100,
        percentage: 90,
        ...overrides,
    });

const mountRecord = (
    exams: ActivityScoreItem[],
    groups: ScoreGroup[] = [{ seasonName: 'Season 1', exams }],
) =>
    mount(ActivityRecordSheet, {
        props: { open: true, groups },
        global: {
            // Portal and focus behavior are covered by activities-my-scores-drawer.test.ts.
            stubs: {
                Sheet: slotStub,
                SheetContent: slotStub,
                SheetHeader: slotStub,
                SheetTitle: slotStub,
                SheetDescription: slotStub,
            },
        },
    });

let wrapper: ReturnType<typeof mountRecord>;

afterEach(() => {
    wrapper?.unmount();
    vi.restoreAllMocks();
});

const selector = (surface: string, key: string) =>
    `[data-test="activity-record-${surface}"][data-component="${key}"]`;
const text = (value: string) => value.replace(/\s+/g, ' ').trim();

function expectComponent(
    key: string,
    label: string,
    score: string,
    percentage: string,
    scope = '',
    term = '',
    cumulative = { score, percentage },
) {
    const period = term ? `[data-term="${term}"]` : '';
    const screen = wrapper.get(selector('table', key) + scope + period);
    expect(
        text(screen.get('[data-test="activity-record-total-cell"]').text()),
    ).toBe(score);
    expect(screen.get('[data-test="activity-record-total-th"]').text()).toBe(
        'Total',
    );
    expect(screen.findAll('tfoot tr')).toHaveLength(1);
    expect(
        screen.findAll('[data-test="activity-record-total-cell"]'),
    ).toHaveLength(1);
    expect(screen.findAll('thead th').map((heading) => heading.text())).toEqual(
        ['Activity', 'Score'],
    );
    expect(screen.findAll('thead th[scope="col"]')).toHaveLength(2);
    expect(screen.text()).not.toContain('%');
    expect(
        screen.element.parentElement?.parentElement
            ?.querySelector('h5')
            ?.textContent?.trim(),
    ).toBe(label);
    expect(
        wrapper
            .find('[data-test="activity-record-component-summary"]')
            .exists(),
    ).toBe(false);
    expect(
        screen.element.parentElement?.parentElement?.querySelectorAll('tfoot'),
    ).toHaveLength(1);

    const print = wrapper.get(
        selector('print-component-table', key) + scope + period,
    );
    const footer = print.findAll('tfoot td').map((cell) => text(cell.text()));
    expect(footer.slice(0, 3)).toEqual([`${label} Total:`, score, percentage]);
    expect(print.findAll('tfoot tr')).toHaveLength(1);
    expect(text(print.element.parentElement?.textContent ?? '')).not.toContain(
        'Subtotal:',
    );
    const summary = wrapper.get(
        selector('print-component-summary', key) + scope,
    );
    expect(summary.get('span').text()).toBe(`${label} Total:`);
    expect(text(summary.get('span:nth-child(2)').text())).toBe(
        `${cumulative.score} pts (${cumulative.percentage})`,
    );
}

function expectComponentCount(count: number) {
    for (const surface of [
        'table',
        'print-component-table',
        'print-component-summary',
    ]) {
        expect(
            wrapper.findAll(`[data-test="activity-record-${surface}"]`),
        ).toHaveLength(count);
    }
}

async function clickButton(label: string) {
    const button = wrapper
        .findAll('button')
        .find((item) => text(item.text()) === label);
    expect(button, `Button ${label}`).toBeDefined();
    await button!.trigger('click');
}

function expectTitles(titles: string[]) {
    expect(
        wrapper
            .findAll('[data-test="activity-record-cell"]')
            .map((cell) => cell.attributes('aria-label')),
    ).toEqual(titles);
    expect(
        wrapper
            .findAll(
                '[data-test="activity-record-print-component-table"] tbody tr',
            )
            .map((row) => row.get('td:nth-child(2) > div').text()),
    ).toEqual(titles);
}

const sectionScope = (
    name: string,
    id: number | 'unassigned',
    season = 'Season 1',
) =>
    `[data-section="${name}"][data-section-id="${id}"][data-season="${season}"]`;

function expectSections(
    expected: {
        name: string;
        id: number | 'unassigned';
        season?: string;
        term: string;
        titles: string[];
    }[],
) {
    for (const surface of ['section', 'print-section']) {
        const sections = wrapper.findAll(
            `[data-test="activity-record-${surface}"]`,
        );
        expect(sections).toHaveLength(expected.length);
        sections.forEach((section, index) => {
            const {
                name,
                id,
                season = 'Season 1',
                term,
                titles,
            } = expected[index];
            expect(section.attributes('data-section')).toBe(name);
            expect(section.attributes('data-section-id')).toBe(String(id));
            expect(section.attributes('data-season')).toBe(season);
            expect(text(section.get('h4').text())).toBe(
                `Section: ${name} · ${season}`,
            );
            expect(
                text(
                    section.element.parentElement?.querySelector('h3')
                        ?.textContent ?? '',
                ),
            ).toBe(surface === 'section' ? term : `Period: ${term}`);
            const tables = section.findAll('table');
            expect(tables.length).toBeGreaterThan(0);
            for (const table of tables) {
                expect(table.attributes('data-term')).toBe(term);
                expect(table.attributes('data-section-id')).toBe(String(id));
                expect(table.attributes('data-season')).toBe(season);
            }
            expect(
                surface === 'section'
                    ? section
                          .findAll('[data-test="activity-record-cell"]')
                          .map((cell) => cell.attributes('aria-label'))
                    : section
                          .findAll('tbody tr')
                          .map((row) =>
                              row.get('td:nth-child(2) > div').text(),
                          ),
            ).toEqual(titles);
        });
    }
}

describe('ActivityRecordSheet section grouping', () => {
    it('keeps two sections separate within Prelims and First Quarter across every component and summary', () => {
        const sections = [
            {
                id: 11,
                name: 'BSIT 1-A',
                scores: [40, 90, 30],
                maxima: [50, 100, 40],
                percentages: ['80%', '90%', '75%'],
            },
            {
                id: 22,
                name: 'BSIT 1-B',
                scores: [12, 30, 10],
                maxima: [20, 50, 40],
                percentages: ['60%', '60%', '25%'],
            },
        ];
        const components = [
            { key: 'written', label: 'Written Activities', make: activity },
            {
                key: 'performance:Performance Task',
                label: 'Performance Tasks',
                make: task,
            },
            {
                key: 'performance:Laboratory',
                label: 'Laboratory',
                make: (overrides: Partial<ActivityScoreItem>) =>
                    task({ ...overrides, activity_type: 'Laboratory' }),
            },
        ];
        const periods = ['Prelims', 'First Quarter'];
        const exams = periods.flatMap((term, periodIndex) =>
            sections.flatMap((section) =>
                components.map((component, index) =>
                    component.make({
                        id: `${term}-${section.id}-${index}`,
                        title: `${term} ${section.name} ${component.label}`,
                        section_id: section.id,
                        section_name: section.name,
                        term,
                        score: section.scores[index] * (periodIndex + 1),
                        total_points: section.maxima[index] * (periodIndex + 1),
                        percentage: Number.parseInt(section.percentages[index]),
                    }),
                ),
            ),
        );
        wrapper = mountRecord(exams);
        expectSections(
            periods.flatMap((term) =>
                sections.map((section) => ({
                    ...section,
                    term,
                    titles: components.map(
                        (component) =>
                            `${term} ${section.name} ${component.label}`,
                    ),
                })),
            ),
        );
        for (const surface of ['table', 'print-component-table']) {
            expect(
                wrapper.findAll(`[data-test="activity-record-${surface}"]`),
            ).toHaveLength(12);
        }
        for (const surface of ['print-component-summary']) {
            expect(
                wrapper.findAll(`[data-test="activity-record-${surface}"]`),
            ).toHaveLength(6);
        }
        periods.forEach((term, periodIndex) => {
            for (const section of sections) {
                components.forEach((component, index) => {
                    expectComponent(
                        component.key,
                        component.label,
                        `${section.scores[index] * (periodIndex + 1)} / ${section.maxima[index] * (periodIndex + 1)}`,
                        section.percentages[index],
                        sectionScope(section.name, section.id),
                        term,
                        {
                            score: `${section.scores[index] * 3} / ${section.maxima[index] * 3}`,
                            percentage: section.percentages[index],
                        },
                    );
                });
            }
        });
    });

    it('keeps identical section names with different IDs separate', () => {
        wrapper = mountRecord([
            activity({ section_id: 11, title: 'First section quiz' }),
            activity({
                id: 2,
                section_id: 22,
                title: 'Second section quiz',
                score: 15,
                total_points: 20,
                percentage: 75,
            }),
        ]);
        expectComponentCount(2);
        expectSections([
            {
                id: 11,
                name: 'BSIT 1-A',
                term: 'Prelims',
                titles: ['First section quiz'],
            },
            {
                id: 22,
                name: 'BSIT 1-A',
                term: 'Prelims',
                titles: ['Second section quiz'],
            },
        ]);
        expectComponent(
            'written',
            'Written Activities',
            '40 / 50',
            '80%',
            sectionScope('BSIT 1-A', 11),
        );
        expectComponent(
            'written',
            'Written Activities',
            '15 / 20',
            '75%',
            sectionScope('BSIT 1-A', 22),
        );
    });

    it('keeps identical section IDs and names separate across seasons', () => {
        wrapper = mountRecord(
            [],
            [
                {
                    seasonName: 'Season 1',
                    exams: [
                        activity({
                            section_id: 11,
                            title: 'First season quiz',
                        }),
                    ],
                },
                {
                    seasonName: 'Season 2',
                    exams: [
                        activity({
                            id: 2,
                            section_id: 11,
                            title: 'Second season quiz',
                            score: 15,
                            total_points: 20,
                            percentage: 75,
                        }),
                    ],
                },
            ],
        );
        expectComponentCount(2);
        expectSections([
            {
                id: 11,
                name: 'BSIT 1-A',
                season: 'Season 1',
                term: 'Prelims',
                titles: ['First season quiz'],
            },
            {
                id: 11,
                name: 'BSIT 1-A',
                season: 'Season 2',
                term: 'Prelims',
                titles: ['Second season quiz'],
            },
        ]);
        expectComponent(
            'written',
            'Written Activities',
            '40 / 50',
            '80%',
            sectionScope('BSIT 1-A', 11, 'Season 1'),
        );
        expectComponent(
            'written',
            'Written Activities',
            '15 / 20',
            '75%',
            sectionScope('BSIT 1-A', 11, 'Season 2'),
        );
    });

    it('falls back to names for missing IDs and labels null sections as unassigned', () => {
        wrapper = mountRecord([
            activity({ title: 'Legacy quiz' }),
            activity({
                id: 2,
                title: 'Legacy follow-up',
                section_name: ' BSIT 1-A ',
            }),
            activity({
                id: 3,
                title: 'Other legacy quiz',
                section_name: 'BSIT 1-B',
                score: 15,
                total_points: 20,
                percentage: 75,
            }),
            activity({
                id: 4,
                title: 'Unnamed legacy quiz',
                section_name: null,
                score: 10,
                total_points: 20,
                percentage: 50,
            }),
            task({
                section_id: null,
                section_name: 'Stale section name',
                title: 'Unassigned task',
            }),
        ]);
        expectSections([
            {
                id: 'unassigned',
                name: 'BSIT 1-A',
                term: 'Prelims',
                titles: ['Legacy quiz', 'Legacy follow-up'],
            },
            {
                id: 'unassigned',
                name: 'BSIT 1-B',
                term: 'Prelims',
                titles: ['Other legacy quiz'],
            },
            {
                id: 'unassigned',
                name: 'General / Unassigned',
                term: 'Prelims',
                titles: ['Unnamed legacy quiz'],
            },
            {
                id: 'unassigned',
                name: 'General / Unassigned',
                term: 'Prelims',
                titles: ['Unassigned task'],
            },
        ]);
        expectComponentCount(4);
        expectComponent(
            'written',
            'Written Activities',
            '80 / 100',
            '80%',
            sectionScope('BSIT 1-A', 'unassigned'),
        );
        expectComponent(
            'written',
            'Written Activities',
            '15 / 20',
            '75%',
            sectionScope('BSIT 1-B', 'unassigned'),
        );
        expectComponent(
            'written',
            'Written Activities',
            '10 / 20',
            '50%',
            sectionScope('General / Unassigned', 'unassigned'),
        );
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '90 / 100',
            '90%',
            sectionScope('General / Unassigned', 'unassigned'),
        );
        expect(
            wrapper.find('[data-section="Stale section name"]').exists(),
        ).toBe(false);
    });

    it('preserves section groups under combined filters without empty sections or changing cumulative summaries', async () => {
        wrapper = mountRecord([
            activity({ section_id: 11, title: 'Shared quiz A' }),
            activity({
                id: 2,
                section_id: 22,
                section_name: 'BSIT 1-B',
                title: 'Shared quiz B',
                score: 15,
                total_points: 20,
                percentage: 75,
            }),
            task({
                section_id: 11,
                title: 'Quarter task',
                term: 'First Quarter',
            }),
            task({
                id: 'task-2',
                section_id: 22,
                section_name: 'BSIT 1-B',
                title: 'Missed task',
                is_missed: true,
            }),
        ]);
        const summaries = () =>
            ['print-component-summary'].map((surface) =>
                wrapper
                    .findAll(`[data-test="activity-record-${surface}"]`)
                    .map((summary) => ({
                        section: summary.attributes('data-section-id'),
                        component: summary.attributes('data-component'),
                        text: text(summary.text()),
                    })),
            );
        const originalSummaries = summaries();
        const first = {
            id: 11,
            name: 'BSIT 1-A',
            term: 'Prelims',
            titles: ['Shared quiz A'],
        };
        const second = {
            id: 22,
            name: 'BSIT 1-B',
            term: 'Prelims',
            titles: ['Shared quiz B'],
        };
        const search = wrapper.get(
            'input[placeholder="Search activity by title or section..."]',
        );
        await search.setValue('Shared');
        expectSections([first, second]);
        expectComponent(
            'written',
            'Written Activities',
            '40 / 50',
            '80%',
            sectionScope('BSIT 1-A', 11),
        );
        expectComponent(
            'written',
            'Written Activities',
            '15 / 20',
            '75%',
            sectionScope('BSIT 1-B', 22),
        );
        await clickButton('Prelims');
        expectSections([first, second]);
        await search.setValue('BSIT 1-B');
        expectSections([
            { ...second, titles: ['Shared quiz B', 'Missed task'] },
        ]);
        await search.setValue('Missed');
        expectSections([{ ...second, titles: ['Missed task'] }]);
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '0 / 100',
            '0%',
            sectionScope('BSIT 1-B', 22),
        );
        await clickButton('First Quarter');
        expectSections([]);
        expectTitles([]);
        expect(wrapper.text()).toContain('No activities match the filter');
        expect(summaries()).toEqual(originalSummaries);
        await search.setValue('');
        expectSections([
            { ...first, term: 'First Quarter', titles: ['Quarter task'] },
        ]);
        await clickButton('All Periods');
        expectSections([
            first,
            { ...second, titles: ['Shared quiz B', 'Missed task'] },
            { ...first, term: 'First Quarter', titles: ['Quarter task'] },
        ]);
        expect(summaries()).toEqual(originalSummaries);
    });
});

describe('ActivityRecordSheet component totals', () => {
    it('keeps written 40/50 and performance 90/100 separate on screen and print', async () => {
        wrapper = mountRecord([activity(), task()]);
        expectComponentCount(2);
        expectComponent('written', 'Written Activities', '40 / 50', '80%');
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '90 / 100',
            '90%',
        );
        for (const [key, title, fraction] of [
            ['written', 'Written quiz', '40 / 50'],
            ['performance:Performance Task', 'Practical task', '90 / 100'],
        ]) {
            const screen = wrapper.get(selector('table', key));
            expect(screen.findAll('tbody tr')).toHaveLength(1);
            expect(screen.get('tbody th[scope="row"]').text()).toBe(title);
            expect(screen.get('tbody th[scope="row"]').find('p').exists()).toBe(
                false,
            );
            expect(
                screen
                    .get('[data-test="activity-record-cell"]')
                    .find('button')
                    .exists(),
            ).toBe(false);
            expect(
                text(screen.get('[data-test="activity-record-cell"]').text()),
            ).toBe(fraction);
            const rows = wrapper
                .get(selector('print-component-table', key))
                .findAll('tbody tr');
            expect(rows).toHaveLength(1);
            expect(rows[0].text()).toContain(title);
            expect(text(rows[0].get('td:nth-child(4)').text())).toBe(fraction);
        }
        expect(wrapper.text()).not.toContain('130 / 150');
        const print = vi.spyOn(window, 'print').mockImplementation(() => {});
        await clickButton('Print Record');
        expect(print).toHaveBeenCalledOnce();
    });

    it('keeps College Laboratory and Presentation labels and totals distinct', () => {
        wrapper = mountRecord([
            task({
                activity_type: 'Laboratory',
                title: 'College lab',
                school_level: 'College',
                score: 35,
                total_points: 50,
            }),
            task({
                id: 'task-2',
                activity_type: 'Presentation',
                title: 'College presentation',
                school_level: 'College',
                score: 72,
                total_points: 80,
            }),
        ]);
        expectComponentCount(2);
        expectComponent(
            'performance:Laboratory',
            'Laboratory',
            '35 / 50',
            '70%',
        );
        expectComponent(
            'performance:Presentation',
            'Presentation',
            '72 / 80',
            '90%',
        );
        expect(
            wrapper.get(selector('table', 'performance:Laboratory')).text(),
        ).not.toContain('College presentation');
        expect(
            wrapper
                .get(
                    selector(
                        'print-component-table',
                        'performance:Presentation',
                    ),
                )
                .text(),
        ).not.toContain('College lab');
        expect(wrapper.text()).not.toContain('107 / 130');
    });

    it.each(['written', 'performance'] as const)(
        'shows only populated %s component',
        (category) => {
            wrapper = mountRecord([
                category === 'written' ? activity() : task(),
            ]);
            expectComponentCount(1);
            if (category === 'written') {
                expectComponent(
                    'written',
                    'Written Activities',
                    '40 / 50',
                    '80%',
                );
                expect(wrapper.text()).not.toContain('Performance Tasks');
            } else {
                expectComponent(
                    'performance:Performance Task',
                    'Performance Tasks',
                    '90 / 100',
                    '90%',
                );
                expect(wrapper.text()).not.toContain('Written Activities');
            }
        },
    );

    it.each([
        { category: 'written', missed: 'flagged' },
        { category: 'performance', missed: 'flagged' },
        { category: 'written', missed: 'closed' },
        { category: 'performance', missed: 'closed' },
    ] as const)(
        'counts $missed missed positive score as zero only in $category denominator',
        ({ category, missed }) => {
            const make = category === 'written' ? activity : task;
            wrapper = mountRecord([
                activity(),
                task(),
                make({
                    id: category === 'written' ? 2 : 'task-2',
                    title: 'Missed positive score',
                    score: 20,
                    total_points: 50,
                    percentage: 40,
                    is_missed: missed === 'flagged',
                    submitted: missed === 'flagged',
                    state: missed === 'flagged' ? 'completed' : 'closed',
                }),
            ]);
            expectComponentCount(2);
            expectComponent(
                'written',
                'Written Activities',
                category === 'written' ? '40 / 100' : '40 / 50',
                category === 'written' ? '40%' : '80%',
            );
            expectComponent(
                'performance:Performance Task',
                'Performance Tasks',
                category === 'performance' ? '90 / 150' : '90 / 100',
                category === 'performance' ? '60%' : '90%',
            );
            expect(
                text(
                    wrapper
                        .get(
                            '[data-test="activity-record-cell"][aria-label="Missed positive score"]',
                        )
                        .text(),
                ),
            ).toBe('0 / 50');
            const missedCell = wrapper.get(
                '[data-test="activity-record-cell"][aria-label="Missed positive score"]',
            );
            expect(
                missedCell.element.parentElement
                    ?.querySelector('th p')
                    ?.textContent?.trim(),
            ).toBe('Missed');
            const row = wrapper
                .findAll(
                    '[data-test="activity-record-print-component-table"] tbody tr',
                )
                .find((row) => row.text().includes('Missed positive score'))!;
            expect(
                row
                    .findAll('td')
                    .slice(3)
                    .map((cell) => text(cell.text())),
            ).toEqual(['0 / 50', '0.0%', 'Missed']);
            expect(
                wrapper.find('[data-test="activity-status-filters"]').exists(),
            ).toBe(false);
        },
    );

    it('shows pending review below its title without counting its score or offering an open action', async () => {
        wrapper = mountRecord([
            activity(),
            task({
                title: 'Pending practical',
                is_pending_review: true,
                submitted: true,
                score: null,
                percentage: null,
                state: 'in_progress',
            }),
        ]);
        expectComponent('written', 'Written Activities', '40 / 50', '80%');
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '0 / 100',
            '0%',
        );
        const table = wrapper.get(
            selector('table', 'performance:Performance Task'),
        );
        const heading = table.get('tbody th[scope="row"]');
        expect(heading.get('span').text()).toBe('Pending practical');
        expect(heading.get('p').text()).toBe('Pending Review');
        expect(heading.find('button').exists()).toBe(false);
        expect(text(table.get('tbody td').text())).toBe('— / 100');
        const print = wrapper.get(
            selector('print-component-table', 'performance:Performance Task'),
        );
        expect(
            print
                .findAll('tbody td')
                .slice(3)
                .map((cell) => text(cell.text())),
        ).toEqual(['— / 100', '—', 'Pending Review']);
        expect(
            wrapper.find('[data-test="activity-status-filters"]').exists(),
        ).toBe(false);
    });

    it('leaves blank open score unmissed and outside denominators; preserves filters and exam actions', async () => {
        wrapper = mountRecord([
            activity(),
            task(),
            activity({
                id: 2,
                title: 'Open quiz',
                term: 'Finals',
                section_name: 'BSIT 2-B',
                score: null,
                percentage: null,
                total_points: 200,
                submitted: false,
                state: 'open',
            }),
        ]);
        expectComponent('written', 'Written Activities', '40 / 50', '80%');
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '90 / 100',
            '90%',
        );
        expect(
            text(
                wrapper
                    .get(
                        '[data-test="activity-record-cell"][aria-label="Open quiz"]',
                    )
                    .text(),
            ),
        ).toBe('— / 200');
        const openPrint = wrapper.get(
            `${selector('print-component-table', 'written')}[data-term="Finals"] tbody tr`,
        );
        expect(
            openPrint
                .findAll('td')
                .slice(3)
                .map((cell) => text(cell.text())),
        ).toEqual(['— / 200', '—', 'Open / Untaken']);
        expect(
            wrapper.find('[data-test="activity-status-filters"]').exists(),
        ).toBe(false);

        await wrapper
            .get('button[aria-label="Review answers for Written quiz"]')
            .trigger('click');
        await wrapper
            .get('button[aria-label="Open Open quiz"]')
            .trigger('click');
        expect(wrapper.emitted('review')).toEqual([[1]]);
        expect(wrapper.emitted('openExam')).toEqual([[2]]);
        expect(
            wrapper
                .get(
                    `${selector('table', 'performance:Performance Task')} tbody th[scope="row"]`,
                )
                .find('button')
                .exists(),
        ).toBe(false);

        await clickButton('Finals');
        expectTitles(['Open quiz']);
        await clickButton('All Periods');
        const search = wrapper.get(
            'input[placeholder="Search activity by title or section..."]',
        );
        await search.setValue('practical');
        expectTitles(['Practical task']);
        await search.setValue('BSIT 2-B');
        expectTitles(['Open quiz']);
        await search.setValue('Prelims');
        expectTitles(['Written quiz', 'Practical task']);
        await search.setValue('');
        expectTitles(['Written quiz', 'Practical task', 'Open quiz']);
        expectComponent('written', 'Written Activities', '40 / 50', '80%');
        expectComponent(
            'performance:Performance Task',
            'Performance Tasks',
            '90 / 100',
            '90%',
        );
    });
});
