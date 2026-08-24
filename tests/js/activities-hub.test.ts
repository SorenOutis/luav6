import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('activities hub', () => {
    const page = readFileSync(
        join(process.cwd(), 'resources/js/pages/Activities/Index.vue'),
        'utf8',
    );

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
});
