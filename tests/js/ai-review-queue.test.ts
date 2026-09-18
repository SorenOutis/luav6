import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('teacher AI review queue', () => {
    it('offers automatic or manual grading for essay questions', () => {
        const examForm = source(
            'app/Filament/Resources/Exams/Schemas/ExamForm.php',
        );
        const methods = source('app/Enums/EssayGradingMethod.php');

        expect(examForm).toContain("Radio::make('grading_method')");
        expect(methods).toContain('AI grades automatically');
        expect(methods).toContain('Teacher grades manually');
        expect(examForm).toContain("$get('type') === 'essay'");
    });

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

    it('distinguishes automatic AI grading from manual teacher grading', () => {
        const exam = source('resources/js/pages/Exams/Show.vue');

        expect(exam).toContain('isAwaitingTeacherReview');
        expect(exam).toContain('Awaiting teacher review');
        expect(exam).toContain('automatic AI scores are applied as');
        expect(exam).toContain('soon as grading finishes.');
    });
});
