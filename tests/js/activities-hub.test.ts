import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('activities hub', () => {
    const page = readFileSync(
        join(process.cwd(), 'resources/js/pages/Activities/Index.vue'),
        'utf8',
    );

    it('omits the activity record summary card while keeping filters and print totals', () => {
        const record = readFileSync(
            join(
                process.cwd(),
                'resources/js/pages/Activities/Partials/ActivityRecordSheet.vue',
            ),
            'utf8',
        );
        expect(record).not.toContain('KPI Summary Card');
        expect(record).not.toContain('Activities graded');
        expect(record).not.toContain('Unsubmitted deadlines');
        expect(record).not.toContain('Open or pending');
        expect(record).toContain('Completed ({{ completedCount }})');
        expect(record).toContain('Missed ({{ missedCount }})');
        expect(record).toContain('In Progress ({{ inProgressCount }})');
        expect(record).toContain('printable-record');
        expect(record).toContain('totalEarnedPoints.toFixed(1)');
    });

    it('renders the activity cards and keeps their actions wired', () => {
        expect(page).toContain('exam-theme-page');
        expect(page).toContain('exam-card');
        expect(page).toContain('v-for="(exam, eIdx) in seasonGroup.exams"');
        expect(page).toContain('@click="openExam(exam)"');
        expect(page).toContain('import { Head, Link, router, usePoll }');
        expect(page).toContain(':href="examsShow(exam.id).url"');
    });

    it('allows the hub content to scroll so later cards are reachable', () => {
        expect(page).toContain('overflow-y-auto');
        expect(page).toContain('data-lenis-prevent');
        expect(page).not.toContain(
            'flex h-full flex-1 flex-col gap-3 overflow-hidden',
        );
    });

    it('uses immediate animations for cards inside the nested scroll container', () => {
        const contentStart = page.indexOf('<!-- Exams tab -->');
        const contentEnd = page.indexOf('<OnboardingTour', contentStart);
        const examContent = page.slice(contentStart, contentEnd);

        // The hub owns its scroll container. Viewport-only in-view animations
        // can leave the whole season group at opacity 0, hiding every card.
        expect(examContent).toContain(':animate="{ opacity: 1, y: 0 }"');
        expect(examContent).not.toContain(':in-view=');
    });

    it('uses the nested hub stats shape returned by the controller', () => {
        expect(page).toContain(
            'hubStats: {\n        exams: { total: number; pending: number; completed: number };\n    };',
        );
        expect(page).toContain('props.hubStats.exams.total');
    });

    it('offers an Activity Record button wired to the record drawer', () => {
        expect(page).toContain('Activity Record');
        expect(page).toContain('@click="showScoresDrawer = true"');
        expect(page).toContain('<ActivityRecordSheet');
        expect(page).toContain(':open="showScoresDrawer"');
        expect(page).toContain(':groups="(activityScores as any) ?? []"');
        expect(page).toContain('@update:open="showScoresDrawer = $event"');
    });

    it('hides the overview stat cards on mobile', () => {
        // Mobile keeps the Record button in the header; the four-tile
        // overview strip is desktop-only now.
        expect(page).toContain('activities-mobile-stats hidden');
        expect(page).toContain('sm:grid sm:grid-cols-4');
    });
});
