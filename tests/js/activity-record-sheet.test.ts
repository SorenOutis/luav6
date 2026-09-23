import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import type { ActivityScoreItem } from '@/pages/Activities/Partials/ActivityRecordSheet.vue';
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

const mountRecord = (exams: ActivityScoreItem[]) =>
    mount(ActivityRecordSheet, {
        props: { open: true, groups: [{ seasonName: 'Season 1', exams }] },
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
) {
    const screen = wrapper.get(selector('table', key));
    expect(
        screen
            .get('[data-test="activity-record-total-cell"]')
            .findAll('span')
            .map((span) => text(span.text())),
    ).toEqual([score, percentage]);
    expect(
        screen.get('[data-test="activity-record-total-th"]').text(),
    ).toContain(percentage);
    expect(
        screen.element.parentElement?.parentElement
            ?.querySelector('h4')
            ?.textContent?.trim(),
    ).toBe(label);
    expect(
        wrapper
            .get(selector('component-summary', key))
            .findAll('span')
            .map((span) => text(span.text())),
    ).toEqual([`${label}:`, score, `(${percentage})`]);

    const print = wrapper.get(selector('print-component-table', key));
    const footer = print.findAll('tfoot td').map((cell) => text(cell.text()));
    expect(footer.slice(0, 3)).toEqual([`${label} Total:`, score, percentage]);
    expect(text(print.element.parentElement?.textContent ?? '')).toContain(
        `${label} Subtotal: ${score} (${percentage})`,
    );
    const summary = wrapper.get(selector('print-component-summary', key));
    expect(summary.get('span').text()).toBe(`${label} Total:`);
    expect(text(summary.get('span:nth-child(2)').text())).toBe(
        `${score} pts (${percentage})`,
    );
}

function expectComponentCount(count: number) {
    for (const surface of [
        'table',
        'component-summary',
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
            expect(screen.findAll('th[scope="col"]')).toHaveLength(1);
            expect(screen.get('th[scope="col"]').text()).toContain(title);
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
                wrapper.get('[data-test="activity-status-filters"]').text(),
            ).toContain('Missed (1)');
        },
    );

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
        ).toBe('—');
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
            wrapper
                .get('[data-test="activity-status-filters"]')
                .findAll('button')
                .map((button) => text(button.text())),
        ).toEqual([
            'All (3)',
            'Completed (2)',
            'Missed (0)',
            'In Progress (1)',
        ]);

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
                    '[data-test="activity-record-cell"][aria-label="Practical task"]',
                )
                .find('button')
                .exists(),
        ).toBe(false);

        await clickButton('Completed (2)');
        expectTitles(['Written quiz', 'Practical task']);
        expect(
            wrapper
                .get(
                    '[data-test="activity-status-filters"] button[aria-pressed="true"]',
                )
                .text(),
        ).toBe('Completed (2)');
        await clickButton('Missed (0)');
        expectTitles([]);
        expect(wrapper.text()).toContain('No activities match the filter');
        await clickButton('In Progress (1)');
        expectTitles(['Open quiz']);
        await clickButton('All (3)');
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
