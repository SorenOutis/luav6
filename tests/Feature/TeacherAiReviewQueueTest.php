<?php

use App\Filament\Resources\AiQuestionDrafts\Pages\EditAiQuestionDraft;
use App\Models\AiEssayFeedbackDraft;
use App\Models\AiQuestionDraft;
use App\Models\AiReviewEvent;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AiReviewService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function teacherReviewEssayContext(): array
{
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->create([
        'questions' => [[
            'type' => 'essay',
            'text' => 'Explain photosynthesis.',
            'points' => 10,
        ]],
    ]);
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $submission = ExamSubmission::create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => [[
            'question_number' => 1,
            'question_type' => 'essay',
            'question_text' => 'Explain photosynthesis.',
            'points' => 10,
            'answer' => 'Plants convert light energy into chemical energy.',
        ]],
        'status' => 'pending_review',
        'score' => 0,
    ]);

    return [$admin, $student, $exam, $part, $submission];
}

it('requires explicit approval before a generated question draft can be attached', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Photosynthesis review',
        'source_text' => 'Plants use sunlight.',
        'status' => 'ready',
        'review_status' => AiQuestionDraft::REVIEW_AWAITING,
        'review_version' => 1,
        'questions' => [[
            'type' => 'essay',
            'text' => 'Explain photosynthesis.',
            'points' => 5,
        ]],
    ]);

    Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->assertActionVisible('approveReview')
        ->assertActionHidden('attachToExam');

    app(AiReviewService::class)->approveQuestionDraft($draft, $admin);

    Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->assertActionHidden('approveReview')
        ->assertActionVisible('attachToExam');

    expect($draft->refresh()->review_status)->toBe(AiQuestionDraft::REVIEW_APPROVED)
        ->and($draft->reviewed_by)->toBe($admin->id)
        ->and(AiReviewEvent::where('event', 'approved')->count())->toBe(1);
});

it('returns an approved question set to review when the saved questions change', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Approved questions',
        'status' => 'ready',
        'review_status' => AiQuestionDraft::REVIEW_APPROVED,
        'review_version' => 2,
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
        'questions' => [['type' => 'essay', 'text' => 'Old question', 'points' => 5]],
    ]);

    $draft->update([
        'questions' => [['type' => 'essay', 'text' => 'Revised question', 'points' => 5]],
    ]);

    expect($draft->refresh()->review_status)->toBe(AiQuestionDraft::REVIEW_AWAITING)
        ->and($draft->reviewed_by)->toBeNull()
        ->and(AiReviewEvent::where('event', 'approval_revoked_by_edit')->count())->toBe(1);
});

it('stores essay AI output as a private proposal without changing the student score', function () {
    [$admin, , , , $submission] = teacherReviewEssayContext();

    $draft = app(AiReviewService::class)->stageEssayFeedback(
        $submission,
        1,
        ['score' => 7, 'feedback' => 'Add more detail about chlorophyll.'],
        provider: 'openai',
        model: 'gpt-4o-mini',
    );

    expect($draft)->toBeInstanceOf(AiEssayFeedbackDraft::class)
        ->and($draft->review_status)->toBe(AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
        ->and($submission->fresh()->score)->toEqual('0.00')
        ->and(array_key_exists('ai_score', $submission->fresh()->answers[0]))->toBeFalse()
        ->and(array_key_exists('ai_feedback', $submission->fresh()->answers[0]))->toBeFalse()
        ->and(AiReviewEvent::where('event', 'generated_for_review')->count())->toBe(1);

    app(AiReviewService::class)->approveEssayFeedback($draft, $admin);

    expect($submission->fresh()->score)->toEqual('7.00')
        ->and($submission->fresh()->status)->toBe('graded')
        ->and($submission->fresh()->answers[0]['ai_feedback_source'])->toBe('teacher_approved_ai');
});

it('rejects essay feedback without applying it and keeps an audit event', function () {
    [$admin, , , , $submission] = teacherReviewEssayContext();
    $draft = app(AiReviewService::class)->stageEssayFeedback(
        $submission,
        1,
        ['score' => 4, 'feedback' => 'This feedback will be rejected.'],
    );

    app(AiReviewService::class)->rejectEssayFeedback($draft, $admin, 'The proposed score is too low.');

    expect($draft->refresh()->review_status)->toBe(AiEssayFeedbackDraft::STATUS_REJECTED)
        ->and($submission->fresh()->score)->toEqual('0.00')
        ->and(array_key_exists('ai_score', $submission->fresh()->answers[0]))->toBeFalse()
        ->and(AiReviewEvent::where('event', 'rejected')->first()->notes)->toBe('The proposed score is too low.');
});

it('prevents an admin from another workspace reviewing a proposal', function () {
    [, , , , $submission] = teacherReviewEssayContext();
    $draft = app(AiReviewService::class)->stageEssayFeedback(
        $submission,
        1,
        ['score' => 8, 'feedback' => 'A valid proposal.'],
    );
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($otherAdmin);

    expect(fn () => app(AiReviewService::class)->approveEssayFeedback($draft, $otherAdmin))
        ->toThrow(DomainException::class);

    expect($draft->refresh()->review_status)->toBe(AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
        ->and($submission->fresh()->score)->toEqual('0.00');
});

it('supersedes stale feedback instead of applying it to a changed essay', function () {
    [$admin, , , , $submission] = teacherReviewEssayContext();
    $draft = app(AiReviewService::class)->stageEssayFeedback(
        $submission,
        1,
        ['score' => 8, 'feedback' => 'A proposal for the original answer.'],
    );
    $answers = $submission->answers;
    $answers[0]['answer'] = 'The answer changed after generation.';
    $submission->forceFill(['answers' => $answers])->save();

    expect(fn () => app(AiReviewService::class)->approveEssayFeedback($draft, $admin))
        ->toThrow(DomainException::class, 'essay changed');

    expect($draft->refresh()->review_status)->toBe(AiEssayFeedbackDraft::STATUS_SUPERSEDED)
        ->and($submission->fresh()->score)->toEqual('0.00')
        ->and(AiReviewEvent::where('event', 'superseded_stale_answer')->count())->toBe(1);
});
