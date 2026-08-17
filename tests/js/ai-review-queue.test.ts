import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('teacher AI review queue', () => {
    it('requires question approval before attach-to-exam is visible', () => {
        const editPage = source(
            'app/Filament/Resources/AiQuestionDrafts/Pages/EditAiQuestionDraft.php',
        );

        expect(editPage).toContain("Action::make('approveReview')");
        expect(editPage).toContain("Action::make('rejectReview')");
        expect(editPage).toContain(
            'review_status === AiQuestionDraft::REVIEW_APPROVED',
        );
        expect(editPage).toContain('recordQuestionDraftAttached');
    });

    it('provides a dedicated essay feedback review resource', () => {
        const resource = source(
            'app/Filament/Resources/AiEssayFeedbackDrafts/AiEssayFeedbackDraftResource.php',
        );
        const reviewPage = source(
            'app/Filament/Resources/AiEssayFeedbackDrafts/Pages/EditAiEssayFeedbackDraft.php',
        );

        expect(resource).toContain('AI Feedback Review');
        expect(reviewPage).toContain("Action::make('approveFeedback')");
        expect(reviewPage).toContain("Action::make('rejectFeedback')");
        expect(reviewPage).toContain("Action::make('regenerateFeedback')");
    });

    it('tells students their essay is awaiting teacher approval', () => {
        const exam = source('resources/js/pages/Exams/Show.vue');

        expect(exam).toContain('isAwaitingTeacherReview');
        expect(exam).toContain('Awaiting teacher review');
        expect(exam).toContain('feedback remains private until your');
        expect(exam).toContain('teacher reviews and approves it.');
    });
});
