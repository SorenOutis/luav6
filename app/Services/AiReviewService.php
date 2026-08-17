<?php

namespace App\Services;

use App\Models\AiEssayFeedbackDraft;
use App\Models\AiQuestionDraft;
use App\Models\AiReviewEvent;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiReviewService
{
    /** @param array<int, array<string, mixed>> $questions */
    public function submitQuestionDraftForReview(
        AiQuestionDraft $draft,
        array $questions,
        ?string $rawResponse,
    ): AiQuestionDraft {
        return DB::transaction(function () use ($draft, $questions, $rawResponse): AiQuestionDraft {
            $locked = AiQuestionDraft::query()->withoutGlobalScope('workspace')->lockForUpdate()->findOrFail($draft->id);
            $before = $locked->questions ?? [];
            $version = (int) $locked->review_version + 1;
            $locked->skipAutomaticReviewAudit = true;

            $locked->forceFill([
                'questions' => $questions,
                'status' => 'ready',
                'review_status' => AiQuestionDraft::REVIEW_AWAITING,
                'review_version' => $version,
                'submitted_for_review_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'ai_response' => $rawResponse,
                'generated_at' => now(),
                'last_error' => null,
            ])->save();

            $this->event(
                $locked,
                $before === [] ? 'generated_for_review' : 'revised_for_review',
                version: $version,
                before: ['questions' => $before],
                after: ['questions' => $questions],
            );

            return $locked;
        });
    }

    public function approveQuestionDraft(AiQuestionDraft $draft, User $reviewer): AiQuestionDraft
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);

        return DB::transaction(function () use ($draft, $reviewer): AiQuestionDraft {
            $locked = AiQuestionDraft::query()->withoutGlobalScope('workspace')->lockForUpdate()->findOrFail($draft->id);

            if ($locked->review_status === AiQuestionDraft::REVIEW_APPROVED) {
                return $locked;
            }
            if ($locked->status !== 'ready' || empty($locked->questions)) {
                throw new \DomainException('Only a generated question set can be approved.');
            }

            $before = $locked->review_status;
            $locked->forceFill([
                'review_status' => AiQuestionDraft::REVIEW_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->event(
                $locked,
                'approved',
                $reviewer,
                $locked->review_version,
                ['review_status' => $before],
                ['review_status' => AiQuestionDraft::REVIEW_APPROVED, 'questions' => $locked->questions],
            );

            return $locked;
        });
    }

    public function rejectQuestionDraft(AiQuestionDraft $draft, User $reviewer, string $reason): AiQuestionDraft
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);
        $reason = Str::limit(trim($reason), 5000, '');
        if ($reason === '') {
            throw new \DomainException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($draft, $reviewer, $reason): AiQuestionDraft {
            $locked = AiQuestionDraft::query()->withoutGlobalScope('workspace')->lockForUpdate()->findOrFail($draft->id);
            if ($locked->status !== 'ready' || empty($locked->questions)) {
                throw new \DomainException('Only a generated question set can be rejected.');
            }

            $before = $locked->review_status;
            $locked->forceFill([
                'review_status' => AiQuestionDraft::REVIEW_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->event(
                $locked,
                'rejected',
                $reviewer,
                $locked->review_version,
                ['review_status' => $before],
                ['review_status' => AiQuestionDraft::REVIEW_REJECTED],
                $reason,
            );

            return $locked;
        });
    }

    public function recordQuestionDraftAttached(AiQuestionDraft $draft, User $reviewer, int $examId, int $partsCreated): void
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);
        $this->event(
            $draft,
            'attached_to_exam',
            $reviewer,
            $draft->review_version,
            after: ['exam_id' => $examId, 'parts_created' => $partsCreated],
        );
    }

    /**
     * Store AI output as a teacher-only proposal. No student-facing answer,
     * score, progress, or XP value is changed here.
     *
     * @param  array{score?: mixed, feedback?: mixed}  $assessment
     */
    public function stageEssayFeedback(
        ExamSubmission $submission,
        int $questionNumber,
        array $assessment,
        ?string $provider = null,
        ?string $model = null,
        bool $force = false,
    ): ?AiEssayFeedbackDraft {
        $answer = collect($submission->answers ?? [])->firstWhere('question_number', $questionNumber);
        if (! is_array($answer) || ($answer['question_type'] ?? null) !== 'essay') {
            return null;
        }

        $answerText = trim((string) ($answer['answer'] ?? ''));
        $feedback = Str::limit(trim((string) ($assessment['feedback'] ?? '')), 10000, '');
        if ($answerText === '' || ! array_key_exists('score', $assessment) || $feedback === '') {
            return null;
        }

        $workspaceId = (int) (
            $submission->exam()->withoutGlobalScope('workspace')->value('workspace_id')
            ?: app(WorkspaceContext::class)->id()
        );
        if (! $workspaceId) {
            return null;
        }

        $maxPoints = max(0, (float) ($answer['points'] ?? 1));
        $score = max(0, min($maxPoints, (float) $assessment['score']));
        $sourceHash = self::essaySourceHash($answer);

        return DB::transaction(function () use (
            $submission,
            $questionNumber,
            $answer,
            $answerText,
            $feedback,
            $provider,
            $model,
            $force,
            $workspaceId,
            $maxPoints,
            $score,
            $sourceHash,
        ): AiEssayFeedbackDraft {
            $draft = AiEssayFeedbackDraft::query()
                ->withoutGlobalScope('workspace')
                ->where('exam_submission_id', $submission->id)
                ->where('question_number', $questionNumber)
                ->lockForUpdate()
                ->first();

            if ($draft && ! $force) {
                if ($draft->review_status === AiEssayFeedbackDraft::STATUS_APPROVED) {
                    return $draft;
                }
                if (
                    $draft->review_status === AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW
                    && hash_equals($draft->source_hash, $sourceHash)
                ) {
                    return $draft;
                }
                if ($draft->review_status === AiEssayFeedbackDraft::STATUS_REJECTED) {
                    return $draft;
                }
            }

            $before = $draft ? $this->essayPayload($draft) : null;
            $version = (int) ($draft?->generation_version ?? 0) + 1;
            $draft ??= new AiEssayFeedbackDraft;
            $draft->forceFill([
                'workspace_id' => $workspaceId,
                'exam_submission_id' => $submission->id,
                'question_number' => $questionNumber,
                'question_text' => (string) ($answer['question_text'] ?? ''),
                'answer_text' => $answerText,
                'max_points' => $maxPoints,
                'proposed_score' => $score,
                'proposed_feedback' => $feedback,
                'provider' => $provider,
                'model' => $model,
                'source_hash' => $sourceHash,
                'review_status' => AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW,
                'generation_version' => $version,
                'generated_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'last_error' => null,
            ])->save();

            $submission->forceFill([
                'status' => 'pending_review',
                'grading_failed' => false,
            ])->save();

            $this->event(
                $draft,
                $version === 1 ? 'generated_for_review' : 'regenerated_for_review',
                version: $version,
                before: $before,
                after: $this->essayPayload($draft),
            );

            return $draft;
        });
    }

    public function approveEssayFeedback(AiEssayFeedbackDraft $draft, User $reviewer): AiEssayFeedbackDraft
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);
        if (trim((string) $draft->proposed_feedback) === '') {
            throw new \DomainException('Feedback cannot be empty when approving an AI proposal.');
        }

        $result = DB::transaction(function () use ($draft, $reviewer): array {
            $locked = AiEssayFeedbackDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ($locked->review_status === AiEssayFeedbackDraft::STATUS_APPROVED) {
                return ['draft' => $locked, 'graded' => false, 'stale' => false];
            }
            if ($locked->review_status !== AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW) {
                throw new \DomainException('Only feedback awaiting review can be approved.');
            }
            if (trim((string) $locked->proposed_feedback) === '') {
                throw new \DomainException('Feedback cannot be empty when approving an AI proposal.');
            }

            $submission = ExamSubmission::query()->lockForUpdate()->findOrFail($locked->exam_submission_id);
            $answers = $submission->answers ?? [];
            $answerIndex = collect($answers)->search(
                fn ($answer): bool => is_array($answer)
                    && (int) ($answer['question_number'] ?? 0) === $locked->question_number,
            );
            if ($answerIndex === false || ! is_array($answers[$answerIndex])) {
                throw new \DomainException('The essay answer no longer exists.');
            }
            if (! hash_equals($locked->source_hash, self::essaySourceHash($answers[$answerIndex]))) {
                $locked->forceFill([
                    'review_status' => AiEssayFeedbackDraft::STATUS_SUPERSEDED,
                    'last_error' => 'The student answer or question changed after this feedback was generated.',
                ])->save();
                $this->event($locked, 'superseded_stale_answer', $reviewer, $locked->generation_version);

                return ['draft' => $locked, 'graded' => false, 'stale' => true];
            }

            $before = $this->essayPayload($locked);
            $oldScore = array_key_exists('ai_score', $answers[$answerIndex])
                ? (float) $answers[$answerIndex]['ai_score']
                : 0.0;
            $newScore = max(0, min((float) $locked->max_points, (float) $locked->proposed_score));
            $answers[$answerIndex]['ai_score'] = $newScore;
            $answers[$answerIndex]['ai_feedback'] = trim($locked->proposed_feedback);
            $answers[$answerIndex]['ai_feedback_source'] = 'teacher_approved_ai';
            $answers[$answerIndex]['ai_feedback_review_id'] = $locked->id;

            $submission->answers = $answers;
            $submission->score = round((float) $submission->score + ($newScore - $oldScore), 2);
            $graded = $this->allEssaysReviewed($answers);
            $submission->status = $graded ? 'graded' : 'pending_review';
            $submission->grading_failed = false;
            $submission->save();

            $locked->forceFill([
                'review_status' => AiEssayFeedbackDraft::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->event(
                $locked,
                'approved',
                $reviewer,
                $locked->generation_version,
                $before,
                $this->essayPayload($locked),
            );

            return ['draft' => $locked, 'graded' => $graded, 'stale' => false];
        });

        if ($result['stale']) {
            throw new \DomainException('The essay changed after AI generation. Generate a fresh review draft.');
        }

        /** @var AiEssayFeedbackDraft $approved */
        $approved = $result['draft'];
        if ($result['graded']) {
            $submission = $approved->submission()->with(['user', 'exam'])->first();
            if ($submission) {
                app(ExamXpAwardService::class)->awardIfEligible($submission->user, $submission->exam);
            }
        }

        return $approved;
    }

    public function rejectEssayFeedback(AiEssayFeedbackDraft $draft, User $reviewer, string $reason): AiEssayFeedbackDraft
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);
        $reason = Str::limit(trim($reason), 5000, '');
        if ($reason === '') {
            throw new \DomainException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($draft, $reviewer, $reason): AiEssayFeedbackDraft {
            $locked = AiEssayFeedbackDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ($locked->review_status !== AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW) {
                throw new \DomainException('Only feedback awaiting review can be rejected.');
            }

            $before = $this->essayPayload($locked);
            $locked->forceFill([
                'review_status' => AiEssayFeedbackDraft::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
            $locked->submission()->update(['status' => 'pending_review']);

            $this->event(
                $locked,
                'rejected',
                $reviewer,
                $locked->generation_version,
                $before,
                $this->essayPayload($locked),
                $reason,
            );

            return $locked;
        });
    }

    public function requestEssayRegeneration(AiEssayFeedbackDraft $draft, User $reviewer): AiEssayFeedbackDraft
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);

        return DB::transaction(function () use ($draft, $reviewer): AiEssayFeedbackDraft {
            $locked = AiEssayFeedbackDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ($locked->review_status === AiEssayFeedbackDraft::STATUS_APPROVED) {
                throw new \DomainException('Approved feedback must not be regenerated.');
            }

            $before = $this->essayPayload($locked);
            $locked->forceFill([
                'review_status' => AiEssayFeedbackDraft::STATUS_GENERATING,
                'last_error' => null,
            ])->save();
            $this->event(
                $locked,
                'regeneration_requested',
                $reviewer,
                $locked->generation_version,
                $before,
                $this->essayPayload($locked),
            );

            return $locked;
        });
    }

    public function recordEssayRevision(AiEssayFeedbackDraft $draft, User $reviewer, array $before): void
    {
        $this->assertWorkspaceReviewer($reviewer, (int) $draft->workspace_id);
        $this->event(
            $draft,
            'teacher_revised_proposal',
            $reviewer,
            $draft->generation_version,
            $before,
            $this->essayPayload($draft),
        );
    }

    public static function essaySourceHash(array $answer): string
    {
        return hash('sha256', json_encode([
            'question_number' => (int) ($answer['question_number'] ?? 0),
            'question_text' => (string) ($answer['question_text'] ?? ''),
            'answer' => (string) ($answer['answer'] ?? ''),
            'points' => (float) ($answer['points'] ?? 0),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertWorkspaceReviewer(User $reviewer, int $workspaceId): void
    {
        $isAdmin = $reviewer->is_admin && (
            $reviewer->isSuperAdmin()
            || $reviewer->workspaces()
                ->whereKey($workspaceId)
                ->wherePivotIn('role', [Workspace::ROLE_OWNER, Workspace::ROLE_ADMIN])
                ->exists()
        );

        if (! $isAdmin) {
            throw new \DomainException('You are not allowed to review AI content for this workspace.');
        }
    }

    /** @return array<string, mixed> */
    private function essayPayload(AiEssayFeedbackDraft $draft): array
    {
        return [
            'score' => (float) $draft->proposed_score,
            'feedback' => $draft->proposed_feedback,
            'review_status' => $draft->review_status,
            'source_hash' => $draft->source_hash,
        ];
    }

    private function allEssaysReviewed(array $answers): bool
    {
        return collect($answers)
            ->filter(fn ($answer): bool => is_array($answer)
                && ($answer['question_type'] ?? null) === 'essay'
                && trim((string) ($answer['answer'] ?? '')) !== '')
            ->every(fn ($answer): bool => array_key_exists('ai_score', $answer)
                && trim((string) ($answer['ai_feedback'] ?? '')) !== '');
    }

    private function event(
        AiQuestionDraft|AiEssayFeedbackDraft $reviewable,
        string $event,
        ?User $actor = null,
        ?int $version = null,
        ?array $before = null,
        ?array $after = null,
        ?string $notes = null,
    ): AiReviewEvent {
        return $reviewable->reviewEvents()->create([
            'workspace_id' => $reviewable->workspace_id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'version' => $version,
            'before_payload' => $before,
            'after_payload' => $after,
            'notes' => $notes,
        ]);
    }
}
