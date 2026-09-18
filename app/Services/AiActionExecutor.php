<?php

namespace App\Services;

use App\Exceptions\PendingAiActionException;
use App\Models\AiQuestionDraft;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Exam;
use App\Models\PendingAiAction;
use App\Models\Section;
use App\Models\Setting;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds an immutable execution plan for a human-approved AI action.
 *
 * prepare() may perform slow, non-mutating work (question generation). The
 * returned closure performs every durable target write inside the same final
 * transaction that marks the action executed, preventing duplicate writes if
 * an approval request is replayed.
 */
class AiActionExecutor
{
    public function __construct(private readonly AiQuestionGeneratorService $questionGenerator) {}

    /** @return Closure(): string */
    public function prepare(PendingAiAction $action): Closure
    {
        return match ($action->action_type) {
            'create_exam' => $this->prepareCreateExam($action),
            'update_exam' => $this->prepareUpdateExam($action),
            'post_announcement' => $this->preparePostAnnouncement($action),
            'create_assignment' => $this->prepareCreateAssignment($action),
            'generate_exam_questions' => $this->prepareGenerateExamQuestions($action),
            default => throw new PendingAiActionException('This AI action type is no longer supported.'),
        };
    }

    /** @return Closure(): string */
    private function prepareCreateExam(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $sectionId = $payload['section_id'] ?? null;
            if ($sectionId !== null) {
                $section = $this->lockWorkspaceRecord(Section::class, (int) $sectionId, $action, 'The selected section no longer exists in this workspace.');
                $this->assertUnchanged($section, $payload['section_expected_updated_at'] ?? null);
            }

            $examDate = Carbon::parse($payload['exam_date']);

            $exam = Exam::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'exam_date' => $examDate,
                // Keep the legacy alias in sync with the schedule.
                'starts_at' => $examDate,
                'ends_at' => $payload['ends_at'] ?? null,
                'duration_minutes' => (int) $payload['duration_minutes'],
                'status' => 'draft',
                'section_id' => $sectionId,
            ]);

            return "Draft exam created: \"{$exam->title}\" (ID {$exam->id}).";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateExam(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Exam $exam */
            $exam = $this->lockWorkspaceRecord(Exam::class, (int) $payload['exam_id'], $action, 'The exam no longer exists in this workspace.');
            $this->assertUnchanged($exam, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'exam_date' || $field === 'starts_at') {
                    $at = Carbon::parse($value);
                    $exam->exam_date = $at;
                    $exam->starts_at = $at;
                    $changes[] = 'starts → '.$at->format('M d, Y g:i A');
                } elseif ($field === 'ends_at') {
                    $exam->ends_at = $value === null ? null : Carbon::parse($value);
                    $changes[] = 'ends → '.($exam->ends_at?->format('M d, Y g:i A') ?? 'open-ended');
                } elseif ($field === 'duration_minutes') {
                    $exam->duration_minutes = (int) $value;
                    $changes[] = "duration → {$exam->duration_minutes} minutes";
                } elseif ($field === 'status') {
                    $exam->status = $value;
                    $changes[] = "status → {$value}";
                }
            }
            $exam->save();

            return "Exam \"{$exam->title}\" (ID {$exam->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function preparePostAnnouncement(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $announcement = Announcement::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'link' => $payload['link'],
                'is_active' => true,
            ]);

            return "Announcement posted: \"{$announcement->title}\" (ID {$announcement->id}).";
        };
    }

    /** @return Closure(): string */
    private function prepareCreateAssignment(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $sectionIds = collect($payload['section_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();

            if ($sectionIds->isEmpty()) {
                throw new PendingAiActionException('This assignment has no target sections, so it would reach no students.');
            }

            $expectedTimestamps = (array) ($payload['section_expected_updated_at'] ?? []);
            $sections = $sectionIds->map(function (int $sectionId) use ($action, $expectedTimestamps) {
                $section = $this->lockWorkspaceRecord(Section::class, $sectionId, $action, 'One of the selected sections no longer exists in this workspace.');
                $this->assertUnchanged($section, $expectedTimestamps[$sectionId] ?? null);

                return $section;
            });

            // The course is an optional label; targeting is by section.
            $course = null;
            if (! empty($payload['course_id'])) {
                /** @var Course $course */
                $course = $this->lockWorkspaceRecord(Course::class, (int) $payload['course_id'], $action, 'The selected course no longer exists in this workspace.');
                $this->assertUnchanged($course, $payload['course_expected_updated_at'] ?? null);
            }

            $assignment = Assignment::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'due_date' => Carbon::parse($payload['due_date']),
                'course_id' => $course?->id,
            ]);

            $assignment->sections()->sync($sections->pluck('id')->all());
            app(AssignmentRosterService::class)->syncAssignment($assignment);

            $sectionNames = $sections->pluck('name')->implode(', ');

            return "Assignment created: \"{$assignment->title}\" (ID {$assignment->id}) for section(s) {$sectionNames}.";
        };
    }

    /** @return Closure(): string */
    private function prepareGenerateExamQuestions(PendingAiAction $action): Closure
    {
        $payload = $action->payload;
        $currentExam = Exam::query()
            ->withoutGlobalScope('workspace')
            ->whereKey((int) $payload['exam_id'])
            ->where('workspace_id', $action->workspace_id)
            ->first();
        if (! $currentExam) {
            throw new PendingAiActionException('The target exam no longer exists in this workspace.');
        }
        $this->assertUnchanged($currentExam, $payload['expected_updated_at'] ?? null);

        $questions = $this->questionGenerator->generate(
            $payload['source_text'],
            $payload['type_counts'],
            $payload['difficulty'],
            $payload['topic'],
        );

        if ($questions === []) {
            throw new PendingAiActionException('The AI returned no usable questions. Try a shorter source or reduce the requested counts.');
        }

        $points = max(1, (int) ($payload['points'] ?? 1));
        $questions = collect($questions)
            ->map(function (array $question) use ($points): array {
                $question['points'] = max(1, (int) ($question['points'] ?? $points));

                return $question;
            })
            ->all();
        $rawResponse = $this->questionGenerator->lastRawResponse;

        return function () use ($action, $payload, $questions, $rawResponse): string {
            /** @var Exam $exam */
            $exam = $this->lockWorkspaceRecord(Exam::class, (int) $payload['exam_id'], $action, 'The target exam no longer exists in this workspace.');
            $this->assertUnchanged($exam, $payload['expected_updated_at'] ?? null);

            $draft = AiQuestionDraft::query()->create([
                'workspace_id' => $action->workspace_id,
                'user_id' => $action->user_id,
                'admin_id' => $action->user_id,
                'target_exam_id' => $exam->id,
                'title' => ($payload['topic'] ?: $exam->title).' — AI question review',
                'source_filename' => 'Echo approval action '.$action->public_id,
                'source_text' => $payload['source_text'],
                'topic' => $payload['topic'],
                'type_counts' => $payload['type_counts'],
                'difficulty' => $payload['difficulty'],
                'attachment_instructions' => $payload['instructions'],
                'provider' => Setting::get('ai_provider', 'gemini'),
                'status' => 'running',
                'review_status' => AiQuestionDraft::REVIEW_NOT_READY,
            ]);

            app(AiReviewService::class)->submitQuestionDraftForReview(
                $draft,
                $questions,
                $rawResponse,
            );

            return "Created AI question review draft #{$draft->id} for \"{$exam->title}\". No questions were attached; a teacher must review and approve the draft first.";
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function lockWorkspaceRecord(string $modelClass, int $id, PendingAiAction $action, string $message): Model
    {
        $record = $modelClass::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($id)
            ->where('workspace_id', $action->workspace_id)
            ->lockForUpdate()
            ->first();

        if (! $record) {
            throw new PendingAiActionException($message);
        }

        return $record;
    }

    private function assertUnchanged(Model $record, ?string $expectedUpdatedAt): void
    {
        $actual = $record->updated_at?->toJSON();

        if ($expectedUpdatedAt !== $actual) {
            throw new PendingAiActionException('This record changed after the preview was created. Ask Echo to prepare a fresh action before approving it.', 409);
        }
    }
}
