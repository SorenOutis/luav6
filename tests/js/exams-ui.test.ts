import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('exams and parts student shell', () => {
    it('applies the student shell to the activities list', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exam.vue'),
            'utf8',
        );

        expect(page).toContain('student-ui');
        expect(page).toContain('Activities');
        expect(page).toContain('dash-btn');
        expect(page).toContain('min-h-11');
        expect(page).toContain('openExam');
        expect(page).toContain('Review results');
        expect(page).not.toContain('OVERDUE');
        expect(page).not.toContain('REMAINING');
    });

    it('keeps "Review results" locked until the exam is closed', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exam.vue'),
            'utf8',
        );

        // Finishing every part flips `is_locked`, which is not enough on its
        // own — the card must wait for the exam to close.
        expect(page).toContain('const canReviewResults');
        expect(page).toContain("exam.status === 'closed'");
        expect(page).toContain('v-if="canReviewResults(exam)"');
        expect(page).toContain('Results locked');
        expect(page).toContain('isAwaitingClose(exam)');

        // The review fetch and the whole-card tap obey the same gate.
        expect(page).toContain('if (!canReviewResults(exam) ||');
        expect(page).toContain('if (canReviewResults(exam)) {');
        expect(page).not.toContain(
            'v-if="exam.is_locked && hasSubmitted(exam)"\n',
        );
    });

    it('applies the student shell to the exam parts page', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exams/Show.vue'),
            'utf8',
        );
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );

        expect(page).toContain('student-ui');
        expect(page).toContain('exam-part-card');
        expect(page).toContain('All activities');
        expect(page).toContain('dash-title');
        expect(page).toContain("'Start'");
        expect(page).toContain('View XP Earned');
        expect(page).toContain('Accuracy bonus');
        expect(page).toContain('Your exam score remains separate');
        expect(css).toContain('.student-ui.exam-theme-page');
        expect(css).toContain('env(safe-area-inset-left)');
    });

    it('lets the desktop progress chart scroll independently of the question list', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exams/Show.vue'),
            'utf8',
        );

        // Many items overflow the sidebar chart; Lenis must not steal the wheel.
        expect(page).toContain('data-testid="exam-progress-chart"');
        expect(page).toContain('data-lenis-prevent');
        expect(page).toContain('@wheel.stop');
        expect(page).toContain('overflow-y-auto overscroll-contain');

        // Sticky footer is timer / progress / save status only — Submit lives
        // in the sidebar (desktop) or inline (mobile / tablet).
        const stickyStart = page.indexOf('class="exam-sticky-header');
        const stickyEnd = page.indexOf('</transition>', stickyStart);
        const sticky = page.slice(stickyStart, stickyEnd);
        expect(stickyStart).toBeGreaterThan(-1);
        expect(sticky).not.toContain('@click="submitPart"');
        expect(sticky).not.toContain('submit-celebration-btn');
        expect(page).toContain('data-testid="exam-tablet-submit"');
    });
});
