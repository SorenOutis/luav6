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
        expect(page).toContain('MascotEmptyState');
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
        expect(page).toContain('FoxCompanion');
        expect(page).toContain('mascot="activities"');
        expect(page).toContain('Show exam fox message');
        expect(page).toContain('data-test="exam-start-fox"');
        expect(page).toContain('data-test="exam-result-fox"');
        expect(page).toContain('data-test="exam-xp-fox"');
        expect(page).toContain('data-test="exam-submit-warning-fox"');
        expect(page).toContain('data-test="exam-focus-warning-fox"');
        expect(page).toContain('All activities');
        expect(page).toContain('id="exam-title"');
        expect(page).toContain('Start Part');
        expect(page).toContain('View XP Earned');
        expect(page).toContain('Accuracy bonus');
        expect(page).toContain('Your exam score remains separate');
        expect(css).toContain('.student-ui.exam-theme-page');
        expect(css).toContain('env(safe-area-inset-left)');
    });

    it('shows overview metadata, four stats, and accessible controls', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exams/Show.vue'),
            'utf8',
        );
        const overview = page.slice(
            page.indexOf('<!-- ─── HERO BANNER'),
            page.indexOf('<!--  QUESTIONS STATE'),
        );
        const normalized = overview.replace(/\s+/g, ' ');

        for (const field of [
            'exam.term',
            'exam.section_name',
            'exam.set?.title',
        ]) {
            expect(overview).toContain(`v-if="${field}"`);
        }
        expect(overview.match(/<dt\s/g)).toHaveLength(4);
        expect(normalized).toContain('Time Limit');
        expect(normalized).toContain("? 'Score' : 'Max Points'");
        expect(overview).toContain('{{ totalScore }}');
        expect(overview).toContain('{{ totalPossiblePoints }}');
        expect(overview).toContain('data-test="exam-overview-stats"');
        expect(overview).toContain('grid-cols-2');
        expect(overview).toContain('sm:grid-cols-4');
        expect(overview).toContain('gap-x-4 gap-y-4');
        expect(overview).not.toContain('text-[10px]');
        expect(overview).toContain('md:p-6');
        expect(page).toMatch(
            /\.student-ui\.exam-theme-page\.exam-system-font \.exam-hero\s*\{[^}]*padding: 1rem;/,
        );
        expect(page).toMatch(
            /@media \(min-width: 768px\)\s*\{\s*\.student-ui\.exam-theme-page\.exam-system-font \.exam-hero\s*\{\s*padding: 1\.5rem;/,
        );
        expect(page).not.toContain('padding: 1rem 0;');
        expect(overview).toContain(':size="isMdUp ? 64 : 48"');
        expect(page).not.toContain('useAccessibility');
        expect(page).not.toContain('updateDyslexiaMode');
        expect(page).not.toContain('toggleDyslexiaMode');
        expect(page).not.toContain('Dyslexia Font');
        expect(page).toContain('exam-system-font');
        expect(page).toContain('system-ui');
        expect(overview).toContain('role="progressbar"');
        expect(normalized).toContain(
            '!allPartsSubmitted && examStarted && !selectedPart',
        );
        expect(overview).toContain('v-if="examHasEnded"');
        expect(overview).toContain('v-else-if="examUpcoming"');
        expect(overview).not.toMatch(/\bsections\b/i);
    });

    it('presents compact parts with completed-first status and singular-aware counts', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exams/Show.vue'),
            'utf8',
        );
        const parts = page.slice(
            page.indexOf('<!-- PARTS LIST STATE -->'),
            page.indexOf('<!--  QUESTIONS STATE'),
        );
        const normalized = parts.replace(/\s+/g, ' ');

        expect(parts).toContain('Exam Parts');
        expect(parts).toContain('data-test="exam-parts-list"');
        expect(parts).not.toContain('xl:grid-cols-3');
        expect(parts).toContain('min-h-11');
        expect(normalized).toContain(
            "exam.parts.length === 1 ? 'part' : 'parts'",
        );
        expect(normalized).toContain(
            '{{ submittedPartsCount }} of {{ exam.parts.length }} completed',
        );
        expect(parts).toMatch(
            /String\(index \+ 1\)\.padStart\(\s*2,\s*'0',?\s*\)/,
        );
        expect(parts).toContain('v-if="isPartSubmitted(part.id)"');
        expect(parts).toContain('v-else-if="isPartLocked(index)"');
        expect(parts).toContain('Next up');
        expect(parts).toContain('partTitleDisplay(part, index)');
        expect(parts).toContain('line-clamp-2');
        expect(parts).toContain('v-if="getQuestionTypes(part).length"');
        expect(normalized).toContain("? 'question' : 'questions'");
        expect(normalized).toContain(
            "partMaxPoints(part) === 1 ? 'pt' : 'pts'",
        );
        expect(normalized).toContain('@click.stop=" selectPart(part, index) "');
        expect(parts).toContain('<button');
        expect(parts).toContain('focus-visible:outline-2');
        expect(parts).toContain('auto-saved to the server');
        expect(parts).not.toContain('dark:bg-zinc-950/40');
    });

    it('uses enum-derived readable question type labels on the student page', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Exams/Show.vue'),
            'utf8',
        );

        expect(page).toContain('type_label?: string;');
        expect(page).toContain('getQuestionTypeLabel');
        expect(page).toContain(
            'question.type_label ?? formatType(question.type)',
        );
        expect(page).toContain("question.type === 'enumeration'");
        expect(page).toContain('setEnumerationAnswer');
        expect(page).toContain('getQuestionMaxPoints');
        expect(page).toContain('enumeration_items');
        expect(page).toContain("question.type === 'matching'");
        expect(page).toContain('getMatchingAnswer');
        expect(page).toContain('setMatchingAnswer');
        expect(page).toContain('matching_items');
        expect(page).toContain('matching_options');
        expect(page).toContain('Select a match');
        expect(page).not.toContain('{{ formatType(type) }}');
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
