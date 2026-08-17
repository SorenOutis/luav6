<?php

namespace App\Filament\Resources\AiEssayFeedbackDrafts\Pages;

use App\Filament\Resources\AiEssayFeedbackDrafts\AiEssayFeedbackDraftResource;
use App\Jobs\GradeExamSubmissionEssays;
use App\Models\AiEssayFeedbackDraft;
use App\Services\AiReviewService;
use App\Support\AiQueueWorker;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAiEssayFeedbackDraft extends EditRecord
{
    protected static string $resource = AiEssayFeedbackDraftResource::class;

    /** @var array<string, mixed>|null */
    private ?array $proposalBeforeSave = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveFeedback')
                ->label('Approve Feedback')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve this score and feedback?')
                ->modalDescription('This applies the currently saved proposal to the student submission. The student score, feedback, and eligible XP may then update.')
                ->visible(fn ($record): bool => $record?->review_status === AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                ->action(function (): void {
                    $this->persistMountedProposal();
                    app(AiReviewService::class)->approveEssayFeedback($this->record, auth()->user());
                    $this->record->refresh();
                    $this->refreshFormData(['proposed_score', 'proposed_feedback', 'review_status', 'reviewed_by', 'reviewed_at']);

                    Notification::make()
                        ->title('AI feedback approved')
                        ->body('The reviewed score and feedback were applied to the student submission.')
                        ->success()
                        ->send();
                }),

            Action::make('rejectFeedback')
                ->label('Reject Feedback')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record): bool => $record?->review_status === AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                ->form([
                    Textarea::make('reason')
                        ->label('Reason for rejection')
                        ->required()
                        ->maxLength(5000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(AiReviewService::class)->rejectEssayFeedback(
                        $this->record,
                        auth()->user(),
                        (string) $data['reason'],
                    );
                    $this->record->refresh();
                    $this->refreshFormData(['review_status', 'reviewed_by', 'reviewed_at', 'rejection_reason']);

                    Notification::make()
                        ->title('AI feedback rejected')
                        ->body('No score or feedback was applied to the student submission.')
                        ->warning()
                        ->send();
                }),

            Action::make('regenerateFeedback')
                ->label('Regenerate Proposal')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn ($record): bool => in_array($record?->review_status, [
                    AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW,
                    AiEssayFeedbackDraft::STATUS_REJECTED,
                    AiEssayFeedbackDraft::STATUS_SUPERSEDED,
                ], true))
                ->action(function (): void {
                    app(AiReviewService::class)->requestEssayRegeneration($this->record, auth()->user());
                    GradeExamSubmissionEssays::dispatch(
                        submissionId: $this->record->exam_submission_id,
                        forceRegenerate: true,
                        onlyQuestionNumber: $this->record->question_number,
                    );
                    AiQueueWorker::ensureRunning();
                    $this->record->refresh();
                    $this->refreshFormData(['review_status', 'last_error']);

                    Notification::make()
                        ->title('AI feedback regeneration queued')
                        ->body('The replacement will return to this review queue; nothing is applied automatically.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->proposalBeforeSave = [
            'score' => (float) $this->record->proposed_score,
            'feedback' => $this->record->proposed_feedback,
            'review_status' => $this->record->review_status,
            'source_hash' => $this->record->source_hash,
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        if (
            $this->proposalBeforeSave
            && $this->record->review_status === AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW
            && (
                (float) $this->proposalBeforeSave['score'] !== (float) $this->record->proposed_score
                || $this->proposalBeforeSave['feedback'] !== $this->record->proposed_feedback
            )
        ) {
            app(AiReviewService::class)->recordEssayRevision(
                $this->record,
                auth()->user(),
                $this->proposalBeforeSave,
            );
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Proposal saved — still awaiting approval';
    }

    private function persistMountedProposal(): void
    {
        $data = $this->form->getState();
        $before = [
            'score' => (float) $this->record->proposed_score,
            'feedback' => $this->record->proposed_feedback,
            'review_status' => $this->record->review_status,
            'source_hash' => $this->record->source_hash,
        ];

        $this->record->forceFill([
            'proposed_score' => $data['proposed_score'] ?? $this->record->proposed_score,
            'proposed_feedback' => $data['proposed_feedback'] ?? $this->record->proposed_feedback,
        ])->save();

        if (
            (float) $before['score'] !== (float) $this->record->proposed_score
            || $before['feedback'] !== $this->record->proposed_feedback
        ) {
            app(AiReviewService::class)->recordEssayRevision(
                $this->record,
                auth()->user(),
                $before,
            );
        }
    }
}
