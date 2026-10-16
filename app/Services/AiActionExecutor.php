<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Exceptions\PendingAiActionException;
use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\AiQuestionDraft;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Badge;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Grade;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\PendingAiAction;
use App\Models\Reward;
use App\Models\Season;
use App\Models\Section;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\StudentActivityNotification;
use App\Support\GamificationSyncContext;
use App\Support\PlatformMaintenance;
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
            'delete_exam' => $this->prepareDeleteExam($action),
            'record_grade' => $this->prepareRecordGrade($action),
            'update_grade' => $this->prepareUpdateGrade($action),
            'delete_grade' => $this->prepareDeleteGrade($action),
            'grade_submission' => $this->prepareGradeSubmission($action),
            'create_user' => $this->prepareCreateUser($action),
            'update_user' => $this->prepareUpdateUser($action),
            'reset_user_password' => $this->prepareResetUserPassword($action),
            'delete_user' => $this->prepareDeleteUser($action),
            'create_section' => $this->prepareCreateSection($action),
            'update_section' => $this->prepareUpdateSection($action),
            'delete_section' => $this->prepareDeleteSection($action),
            'create_course' => $this->prepareCreateCourse($action),
            'update_course' => $this->prepareUpdateCourse($action),
            'delete_course' => $this->prepareDeleteCourse($action),
            'post_announcement' => $this->preparePostAnnouncement($action),
            'update_announcement' => $this->prepareUpdateAnnouncement($action),
            'delete_announcement' => $this->prepareDeleteAnnouncement($action),
            'create_assignment' => $this->prepareCreateAssignment($action),
            'update_assignment' => $this->prepareUpdateAssignment($action),
            'delete_assignment' => $this->prepareDeleteAssignment($action),
            'create_learning_material' => $this->prepareCreateLearningMaterial($action),
            'update_learning_material' => $this->prepareUpdateLearningMaterial($action),
            'delete_learning_material' => $this->prepareDeleteLearningMaterial($action),
            'create_activity_task' => $this->prepareCreateActivityTask($action),
            'update_activity_task' => $this->prepareUpdateActivityTask($action),
            'delete_activity_task' => $this->prepareDeleteActivityTask($action),
            'record_activity_task_score' => $this->prepareRecordActivityTaskScore($action),
            'award_student_xp' => $this->prepareAwardStudentXp($action),
            'manage_exam_blocks' => $this->prepareManageExamBlocks($action),
            'send_notification' => $this->prepareSendNotification($action),
            'reply_support_ticket' => $this->prepareReplySupportTicket($action),
            'season_admin' => $this->prepareSeasonAdmin($action),
            'manage_badges_rewards' => $this->prepareManageBadgesRewards($action),
            'manage_lesson' => $this->prepareManageLesson($action),
            'manage_lesson_quiz' => $this->prepareManageLessonQuiz($action),
            'generate_exam_questions' => $this->prepareGenerateExamQuestions($action),
            'manage_maintenance' => $this->prepareManageMaintenance($action),
            'export_report' => $this->prepareExportReport($action),
            default => throw new PendingAiActionException('This AI action type is no longer supported.'),
        };
    }

    /** @return Closure(): string */
    private function prepareCreateExam(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $sectionId = $payload['section_id'] ?? null;
            if ($sectionId === null && ! empty($payload['section_name'])) {
                $sectionId = Section::query()
                    ->withoutGlobalScope('workspace')
                    ->where('workspace_id', $action->workspace_id)
                    ->where('name', $payload['section_name'])
                    ->value('id');
            }

            if ($sectionId !== null) {
                $section = $this->lockWorkspaceRecord(Section::class, (int) $sectionId, $action, 'The selected section no longer exists in this workspace.');
                if (! empty($payload['section_expected_updated_at'])) {
                    $this->assertUnchanged($section, $payload['section_expected_updated_at']);
                }
            }

            $examDate = Carbon::parse($payload['exam_date']);

            $exam = Exam::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'term' => $payload['term'] ?? null,
                'exam_date' => $examDate,
                // Keep the legacy alias in sync with the schedule.
                'starts_at' => $examDate,
                'ends_at' => $payload['ends_at'] ?? null,
                'duration_minutes' => (int) ($payload['duration_minutes'] ?? 60),
                'status' => 'draft',
                'section_id' => $sectionId,
            ]);

            if (! empty($payload['blocked_user_ids']) && is_array($payload['blocked_user_ids'])) {
                app(ExamBlockService::class)->sync($exam, $payload['blocked_user_ids']);
            }

            $questionMessage = '';
            if (! empty($payload['questions']) && is_array($payload['questions'])) {
                $rawQuestions = $payload['questions'];

                // Group questions by type so mixed-type exams create structured parts
                $grouped = collect($rawQuestions)->groupBy(fn ($q) => (string) ($q['type'] ?? 'multiple_choice'));

                $partIndex = 1;
                foreach ($grouped as $type => $groupQuestions) {
                    $questionsList = $groupQuestions->values()->all();
                    $label = QuestionType::tryFromStored($type)?->label() ?? 'Questions';
                    $points = (int) ($questionsList[0]['points'] ?? 1);

                    $exam->parts()->create([
                        'title' => "Part {$partIndex}: {$label}",
                        'type' => $type,
                        'instructions' => "Answer all {$label} questions to the best of your ability.",
                        'sort_order' => $partIndex,
                        'points' => $points,
                        'questions' => $questionsList,
                    ]);
                    $partIndex++;
                }

                $count = count($rawQuestions);
                $partsCount = count($grouped);
                $extraParts = $partsCount > 1 ? " across {$partsCount} parts" : '';
                $questionMessage = " with {$count} question(s){$extraParts} attached";
            }

            $blockedMessage = ! empty($payload['blocked_user_ids']) ? ' ('.count($payload['blocked_user_ids']).' student(s) blocked)' : '';

            return "Draft exam created: \"{$exam->title}\" (ID {$exam->id}){$questionMessage}{$blockedMessage}.";
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
                } elseif ($field === 'term') {
                    $exam->term = $value;
                    $changes[] = "term → {$value}";
                }
            }
            $exam->save();

            if (isset($payload['changes']['blocked_user_ids'])) {
                $blockedIds = (array) $payload['changes']['blocked_user_ids'];
                app(ExamBlockService::class)->sync($exam, $blockedIds);
                $changes[] = 'blocked students → '.(count($blockedIds) > 0 ? count($blockedIds).' student(s)' : 'none (open to all)');
            }

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

    /** @return Closure(): string */
    private function prepareDeleteExam(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Exam $exam */
            $exam = $this->lockWorkspaceRecord(Exam::class, (int) $payload['exam_id'], $action, 'The exam no longer exists in this workspace.');
            $this->assertUnchanged($exam, $payload['expected_updated_at'] ?? null);

            $title = $exam->title;
            $id = $exam->id;
            $exam->delete();

            return "Exam \"{$title}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareRecordGrade(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $student = User::query()->whereKey((int) $payload['student_id'])->first();
            if (! $student) {
                throw new PendingAiActionException('The selected student no longer exists.');
            }

            /** @var Section $section */
            $section = $this->lockWorkspaceRecord(Section::class, (int) $payload['section_id'], $action, 'The selected section no longer exists in this workspace.');

            $enrolled = $student->sections()
                ->withoutGlobalScope('workspace')
                ->where('sections.id', $section->id)
                ->exists();

            if (! $enrolled) {
                throw new PendingAiActionException('The student is no longer enrolled in the selected section.');
            }

            $grade = Grade::query()->create([
                'workspace_id' => $action->workspace_id,
                'user_id' => $student->id,
                'section_id' => $section->id,
                'subject' => $payload['subject'],
                'period' => $payload['period'],
                'score' => $payload['score'],
                'max_score' => $payload['max_score'] ?? 100,
                'remarks' => $payload['remarks'] ?? null,
                'recorded_by' => $action->user_id,
            ]);

            return "Grade recorded for {$student->name}: {$grade->subject} ({$grade->period}) — {$grade->score}/{$grade->max_score}.";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateGrade(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Grade $grade */
            $grade = $this->lockWorkspaceRecord(Grade::class, (int) $payload['grade_id'], $action, 'The grade record no longer exists in this workspace.');
            $this->assertUnchanged($grade, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if (in_array($field, ['score', 'max_score', 'subject', 'period', 'remarks'], true)) {
                    $grade->{$field} = $value;
                    $changes[] = "{$field} → {$value}";
                }
            }
            $grade->save();

            $studentName = $grade->student?->name ?? "Student #{$grade->user_id}";

            return "Grade #{$grade->id} for {$studentName} updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteGrade(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Grade $grade */
            $grade = $this->lockWorkspaceRecord(Grade::class, (int) $payload['grade_id'], $action, 'The grade record no longer exists in this workspace.');
            $this->assertUnchanged($grade, $payload['expected_updated_at'] ?? null);

            $id = $grade->id;
            $studentName = $grade->student?->name ?? "Student #{$grade->user_id}";
            $subject = $grade->subject;
            $grade->delete();

            return "Grade #{$id} for {$studentName} ({$subject}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareGradeSubmission(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var ExamSubmission $submission */
            $submission = ExamSubmission::query()
                ->whereKey((int) $payload['submission_id'])
                ->whereHas('exam', fn ($q) => $q->where('workspace_id', $action->workspace_id))
                ->lockForUpdate()
                ->first();

            if (! $submission) {
                throw new PendingAiActionException('The exam submission no longer exists in this workspace.');
            }

            $this->assertUnchanged($submission, $payload['expected_updated_at'] ?? null);

            $submission->score = $payload['score'];
            $submission->status = $payload['status'] ?? 'graded';
            if (array_key_exists('feedback', $payload)) {
                $submission->feedback = $payload['feedback'];
            }
            $submission->save();

            $studentName = $submission->user?->name ?? "Student #{$submission->user_id}";
            $examTitle = $submission->exam?->title ?? "Exam #{$submission->exam_id}";

            return "Exam submission #{$submission->id} for student \"{$studentName}\" on \"{$examTitle}\" graded: score set to {$submission->score}.";
        };
    }

    /** @return Closure(): string */
    private function prepareCreateUser(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $email = strtolower(trim((string) $payload['email']));
            if (User::query()->where('email', $email)->exists()) {
                throw new PendingAiActionException("A user with email \"{$email}\" already exists.");
            }

            $isAdmin = (bool) ($payload['is_admin'] ?? false);
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $email,
                'password' => $payload['password'],
                'is_admin' => $isAdmin,
            ]);

            $role = $isAdmin ? Workspace::ROLE_ADMIN : Workspace::ROLE_STUDENT;
            $user->joinWorkspace((int) $action->workspace_id, $role);

            $sectionIds = (array) ($payload['section_ids'] ?? []);
            if ($sectionIds !== []) {
                $user->sections()->sync($sectionIds);
            }

            if (! $isAdmin) {
                $user->activeSeasonProgress();
            }

            $roleLabel = $isAdmin ? 'Administrator' : 'Student';

            return "User \"{$user->name}\" ({$user->email}) created successfully as {$roleLabel}.";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateUser(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var User $user */
            $user = $this->lockWorkspaceUser((int) $payload['user_id'], $action, 'The selected user no longer exists in this workspace.');

            $this->assertUnchanged($user, $payload['expected_updated_at'] ?? null);

            $changes = $payload['changes'] ?? [];
            if (isset($changes['name'])) {
                $user->name = $changes['name'];
            }
            if (isset($changes['email'])) {
                $user->email = $changes['email'];
            }
            if (isset($changes['password'])) {
                $user->password = $changes['password'];
            }
            if (isset($changes['is_banned'])) {
                $user->is_banned = (bool) $changes['is_banned'];
                $user->ban_reason = $changes['ban_reason'] ?? null;
                $user->banned_at = $user->is_banned ? now() : null;
            }
            $user->save();

            if (isset($changes['section_ids'])) {
                $user->sections()->sync($changes['section_ids']);
            }

            return "User \"{$user->name}\" (#{$user->id}) updated successfully.";
        };
    }

    /** @return Closure(): string */
    private function prepareResetUserPassword(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var User $user */
            $user = $this->lockWorkspaceUser((int) $payload['user_id'], $action, 'The selected user no longer exists in this workspace.');

            $this->assertUnchanged($user, $payload['expected_updated_at'] ?? null);

            $user->password = $payload['password'];
            $user->save();

            return "Password reset successfully for user \"{$user->name}\" ({$user->email}).";
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteUser(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var User $user */
            $user = $this->lockWorkspaceUser((int) $payload['user_id'], $action, 'The selected user no longer exists in this workspace.');

            $this->assertUnchanged($user, $payload['expected_updated_at'] ?? null);

            $name = $user->name;
            $email = $user->email;
            $id = $user->id;

            $user->workspaces()->detach($action->workspace_id);
            $workspaceSectionIds = Section::withoutGlobalScope('workspace')
                ->where('workspace_id', $action->workspace_id)
                ->pluck('id');
            $user->sections()->detach($workspaceSectionIds);

            if (! $user->workspaces()->exists()) {
                $user->delete();
            }

            return "User \"{$name}\" (ID {$id}, {$email}) removed from workspace.";
        };
    }

    /** @return Closure(): string */
    private function prepareCreateSection(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $joinCode = Section::generateUniqueJoinCode();

            $seasonId = $payload['season_id'] ?? null;
            if (! $seasonId && $action->workspace_id) {
                $seasonId = Season::withoutGlobalScope('workspace')
                    ->where('workspace_id', $action->workspace_id)
                    ->where('is_active', true)
                    ->value('id');
            }
            if (! $seasonId) {
                $seasonId = Season::current()?->id;
            }

            $schoolLevel = $payload['school_level'] ?? Section::SCHOOL_LEVEL_COLLEGE;
            $defaultTerms = $schoolLevel === Section::SCHOOL_LEVEL_SENIOR_HIGH
                ? ['First Semester - 1st Quarter', 'First Semester - 2nd Quarter', 'Second Semester - 1st Quarter', 'Second Semester - 2nd Quarter']
                : ['Prelim', 'Midterm', 'Final'];

            $section = Section::query()->create([
                'name' => $payload['name'],
                'school_level' => $schoolLevel,
                'leaderboard_enabled' => $payload['leaderboard_enabled'] ?? true,
                'activity_record_enabled' => true,
                'activity_record_terms' => $defaultTerms,
                'join_code' => $joinCode,
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'season_id' => $seasonId,
            ]);

            return "Class section \"{$section->name}\" (ID {$section->id}) created with join code {$section->join_code}.";
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteSection(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Section $section */
            $section = $this->lockWorkspaceRecord(Section::class, (int) $payload['section_id'], $action, 'The class section no longer exists in this workspace.');
            $this->assertUnchanged($section, $payload['expected_updated_at'] ?? null);

            $name = $section->name;
            $id = $section->id;
            $section->users()->detach();
            $section->delete();

            return "Class section \"{$name}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteAssignment(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Assignment $assignment */
            $assignment = $this->lockWorkspaceRecord(Assignment::class, (int) $payload['assignment_id'], $action, 'The assignment no longer exists in this workspace.');
            $this->assertUnchanged($assignment, $payload['expected_updated_at'] ?? null);

            $title = $assignment->title;
            $id = $assignment->id;
            $assignment->sections()->detach();
            $assignment->delete();

            return "Assignment \"{$title}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteAnnouncement(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Announcement $announcement */
            $announcement = $this->lockWorkspaceRecord(Announcement::class, (int) $payload['announcement_id'], $action, 'The announcement no longer exists in this workspace.');
            $this->assertUnchanged($announcement, $payload['expected_updated_at'] ?? null);

            $title = $announcement->title;
            $id = $announcement->id;
            $announcement->delete();

            return "Announcement \"{$title}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateSection(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Section $section */
            $section = $this->lockWorkspaceRecord(Section::class, (int) $payload['section_id'], $action, 'The section no longer exists in this workspace.');
            $this->assertUnchanged($section, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'name') {
                    $section->name = (string) $value;
                    $changes[] = "name → {$value}";
                } elseif ($field === 'school_level') {
                    $section->school_level = (string) $value;
                    $changes[] = "school_level → {$value}";
                } elseif ($field === 'leaderboard_enabled') {
                    $section->leaderboard_enabled = (bool) $value;
                    $changes[] = 'leaderboard → '.($value ? 'enabled' : 'disabled');
                }
            }
            $section->save();

            return "Section \"{$section->name}\" (ID {$section->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareCreateCourse(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $course = Course::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'total_lessons' => (int) ($payload['total_lessons'] ?? 1),
            ]);

            return "Course created: \"{$course->name}\" (ID {$course->id}).";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateCourse(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Course $course */
            $course = $this->lockWorkspaceRecord(Course::class, (int) $payload['course_id'], $action, 'The course no longer exists in this workspace.');
            $this->assertUnchanged($course, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'name') {
                    $course->name = (string) $value;
                    $changes[] = "name → {$value}";
                } elseif ($field === 'description') {
                    $course->description = $value === null ? null : (string) $value;
                    $changes[] = 'description updated';
                } elseif ($field === 'total_lessons') {
                    $course->total_lessons = (int) $value;
                    $changes[] = "total_lessons → {$value}";
                }
            }
            $course->save();

            return "Course \"{$course->name}\" (ID {$course->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteCourse(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Course $course */
            $course = $this->lockWorkspaceRecord(Course::class, (int) $payload['course_id'], $action, 'The course no longer exists in this workspace.');
            $this->assertUnchanged($course, $payload['expected_updated_at'] ?? null);

            $name = $course->name;
            $id = $course->id;
            $course->users()->detach();
            $course->delete();

            return "Course \"{$name}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateAssignment(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Assignment $assignment */
            $assignment = $this->lockWorkspaceRecord(Assignment::class, (int) $payload['assignment_id'], $action, 'The assignment no longer exists in this workspace.');
            $this->assertUnchanged($assignment, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'title') {
                    $assignment->title = (string) $value;
                    $changes[] = "title → {$value}";
                } elseif ($field === 'description') {
                    $assignment->description = $value === null ? null : (string) $value;
                    $changes[] = 'description updated';
                } elseif ($field === 'due_date') {
                    $due = Carbon::parse($value);
                    $assignment->due_date = $due;
                    $changes[] = 'due_date → '.$due->format('M d, Y g:i A');
                } elseif ($field === 'section_ids') {
                    $sectionIds = collect($value)->map(fn ($id) => (int) $id)->filter()->unique()->all();
                    $assignment->sections()->sync($sectionIds);
                    app(AssignmentRosterService::class)->syncAssignment($assignment);
                    $changes[] = 'sections updated ('.count($sectionIds).' linked)';
                }
            }
            $assignment->save();

            return "Assignment \"{$assignment->title}\" (ID {$assignment->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateAnnouncement(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Announcement $announcement */
            $announcement = $this->lockWorkspaceRecord(Announcement::class, (int) $payload['announcement_id'], $action, 'The announcement no longer exists in this workspace.');
            $this->assertUnchanged($announcement, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'title') {
                    $announcement->title = (string) $value;
                    $changes[] = "title → {$value}";
                } elseif ($field === 'description') {
                    $announcement->description = (string) $value;
                    $changes[] = 'description updated';
                } elseif ($field === 'link') {
                    $announcement->link = $value === null ? null : (string) $value;
                    $changes[] = 'link → '.($value ?? 'none');
                }
            }
            $announcement->save();

            return "Announcement \"{$announcement->title}\" (ID {$announcement->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareCreateLearningMaterial(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $material = LearningMaterial::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'file_path' => $payload['file_path'] ?? 'learning-materials/placeholder.pdf',
                'file_name' => $payload['file_name'] ?? 'document.pdf',
                'file_size' => (int) ($payload['file_size'] ?? 1024),
                'mime_type' => $payload['mime_type'] ?? 'application/pdf',
                'file_extension' => 'pdf',
                'status' => $payload['status'] ?? LearningMaterial::STATUS_PUBLISHED,
            ]);

            $sectionIds = collect($payload['section_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();
            if ($sectionIds !== []) {
                $material->sections()->sync($sectionIds);
            }

            return "Learning material \"{$material->title}\" (ID {$material->id}) was created.";
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateLearningMaterial(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var LearningMaterial $material */
            $material = $this->lockWorkspaceRecord(LearningMaterial::class, (int) $payload['material_id'], $action, 'The learning material no longer exists in this workspace.');
            $this->assertUnchanged($material, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'title') {
                    $material->title = (string) $value;
                    $changes[] = "title → {$value}";
                } elseif ($field === 'description') {
                    $material->description = $value === null ? null : (string) $value;
                    $changes[] = 'description updated';
                } elseif ($field === 'status') {
                    $material->status = (string) $value;
                    $changes[] = "status → {$value}";
                } elseif ($field === 'section_ids') {
                    $sectionIds = collect($value)->map(fn ($id) => (int) $id)->filter()->unique()->all();
                    $material->sections()->sync($sectionIds);
                    $changes[] = 'target sections updated ('.count($sectionIds).' linked)';
                }
            }
            $material->save();

            return "Learning material \"{$material->title}\" (ID {$material->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteLearningMaterial(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var LearningMaterial $material */
            $material = $this->lockWorkspaceRecord(LearningMaterial::class, (int) $payload['material_id'], $action, 'The learning material no longer exists in this workspace.');
            $this->assertUnchanged($material, $payload['expected_updated_at'] ?? null);

            $title = $material->title;
            $id = $material->id;
            $material->sections()->detach();
            $material->delete();

            return "Learning material \"{$title}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareCreateActivityTask(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $section = $this->lockWorkspaceRecord(Section::class, (int) $payload['section_id'], $action, 'The section no longer exists in this workspace.');

            $task = ActivityTask::query()->create([
                'workspace_id' => $action->workspace_id,
                'admin_id' => $action->user_id,
                'section_id' => $section->id,
                'title' => $payload['title'],
                'term' => $payload['term'] ?? ($section->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH ? 'First Semester - 1st Quarter' : 'Midterm'),
                'task_type' => $payload['task_type'] ?? 'Performance Task',
                'max_points' => $payload['max_points'] ?? 100.0,
                'description' => $payload['description'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
            ]);

            $rosterAdded = 0;
            if (! isset($payload['populate_students']) || ! empty($payload['populate_students'])) {
                $students = User::query()
                    ->whereHas('sections', fn ($q) => $q->where('sections.id', $section->id))
                    ->where('is_admin', false)
                    ->get();

                foreach ($students as $student) {
                    ActivityTaskScore::query()->firstOrCreate(
                        [
                            'activity_task_id' => $task->id,
                            'user_id' => $student->id,
                        ],
                        [
                            'score' => null,
                            'is_missed' => false,
                            'graded_by' => $action->user_id,
                        ]
                    );
                    $rosterAdded++;
                }
            }

            $message = "Activity task \"{$task->title}\" (ID {$task->id}) created for section {$section->name}.";
            if ($rosterAdded > 0) {
                $message .= " Added {$rosterAdded} section students to the score sheet roster.";
            }

            return $message;
        };
    }

    /** @return Closure(): string */
    private function prepareUpdateActivityTask(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var ActivityTask $task */
            $task = $this->lockWorkspaceRecord(ActivityTask::class, (int) $payload['task_id'], $action, 'The activity task no longer exists in this workspace.');
            $this->assertUnchanged($task, $payload['expected_updated_at'] ?? null);

            $changes = [];
            foreach ($payload['changes'] as $field => $value) {
                if ($field === 'title') {
                    $task->title = (string) $value;
                    $changes[] = "title → {$value}";
                } elseif ($field === 'task_type') {
                    $task->task_type = (string) $value;
                    $changes[] = "task_type → {$value}";
                } elseif ($field === 'term') {
                    $task->term = (string) $value;
                    $changes[] = "term → {$value}";
                } elseif ($field === 'max_points') {
                    $task->max_points = (float) $value;
                    $changes[] = "max_points → {$value}";
                } elseif ($field === 'due_date') {
                    $task->due_date = $value === null ? null : Carbon::parse($value);
                    $changes[] = 'due_date → '.($value === null ? 'None' : Carbon::parse($value)->format('M d, Y'));
                } elseif ($field === 'description') {
                    $task->description = $value === null ? null : (string) $value;
                    $changes[] = 'description updated';
                }
            }
            $task->save();

            return "Activity task \"{$task->title}\" (ID {$task->id}) updated: ".implode('; ', $changes).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareDeleteActivityTask(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var ActivityTask $task */
            $task = $this->lockWorkspaceRecord(ActivityTask::class, (int) $payload['task_id'], $action, 'The activity task no longer exists in this workspace.');
            $this->assertUnchanged($task, $payload['expected_updated_at'] ?? null);

            $title = $task->title;
            $id = $task->id;
            $task->scores()->delete();
            $task->delete();

            return "Activity task \"{$title}\" (ID {$id}) was deleted.";
        };
    }

    /** @return Closure(): string */
    private function prepareRecordActivityTaskScore(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            if (! empty($payload['all_students']) && ! empty($payload['students'])) {
                /** @var ActivityTask $task */
                $task = $this->lockWorkspaceRecord(ActivityTask::class, (int) $payload['activity_task_id'], $action, 'The activity task no longer exists in this workspace.');

                $count = 0;
                foreach ($payload['students'] as $st) {
                    $student = $this->lockWorkspaceUser((int) $st['student_id'], $action, 'A student no longer belongs to this workspace.');
                    ActivityTaskScore::query()->updateOrCreate(
                        [
                            'activity_task_id' => $task->id,
                            'user_id' => $student->id,
                        ],
                        [
                            'score' => $st['score'] ?? $payload['score'] ?? null,
                            'is_missed' => $st['is_missed'] ?? false,
                            'remarks' => $st['remarks'] ?? $payload['remarks'] ?? null,
                            'graded_by' => $action->user_id,
                        ]
                    );
                    $count++;
                }

                return "Activity task scores recorded for {$count} students in section on \"{$task->title}\".";
            }

            $student = $this->lockWorkspaceUser((int) $payload['student_id'], $action, 'The student no longer belongs to this workspace.');

            $items = ! empty($payload['items']) && is_array($payload['items'])
                ? $payload['items']
                : [
                    [
                        'activity_task_id' => $payload['activity_task_id'],
                        'score' => $payload['score'],
                        'remarks' => $payload['remarks'] ?? null,
                    ],
                ];

            $recorded = [];
            foreach ($items as $item) {
                /** @var ActivityTask $task */
                $task = $this->lockWorkspaceRecord(ActivityTask::class, (int) $item['activity_task_id'], $action, 'The activity task no longer exists in this workspace.');

                $score = ActivityTaskScore::query()->updateOrCreate(
                    [
                        'activity_task_id' => $task->id,
                        'user_id' => $student->id,
                    ],
                    [
                        'score' => $item['score'],
                        'is_missed' => $item['is_missed'] ?? false,
                        'remarks' => $item['remarks'] ?? null,
                        'graded_by' => $action->user_id,
                    ]
                );

                $max = (float) $task->max_points;
                $pct = $max > 0 ? round(((float) $score->score / $max) * 100, 1) : 0;
                $recorded[] = "\"{$task->title}\" ({$score->score}/{$task->max_points} — {$pct}%)";
            }

            return "Activity task score recorded for {$student->name}: ".implode(', ', $recorded).'.';
        };
    }

    /** @return Closure(): string */
    private function prepareAwardStudentXp(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $student = $this->lockWorkspaceUser((int) $payload['student_id'], $action, 'The student no longer belongs to this workspace.');

            $amountXp = (float) $payload['amount_xp'];
            $amountPoints = (float) ($payload['amount_points'] ?? 0);
            $reason = (string) $payload['reason'];

            $sectionId = $student->sections()->where('workspace_id', $action->workspace_id)->value('sections.id')
                ?? $student->sections()->first()?->id;

            if ($sectionId) {
                $progress = $student->activeSectionProgress($sectionId);
                if ($progress) {
                    app(GamificationSyncContext::class)->withoutAutomaticHistory(function () use ($progress, $amountXp, $amountPoints): void {
                        if ($amountXp > 0) {
                            $progress->increment('exp', $amountXp);
                        }
                        if ($amountPoints > 0) {
                            $progress->increment('points', $amountPoints);
                        }
                        $progress->save();
                    });
                }
            }

            $student->recordGamificationHistory(
                $amountXp,
                $amountPoints,
                'Admin Award',
                $reason,
                $sectionId,
                null,
                $action->user_id,
            );

            return "Awarded +{$amountXp} XP and +{$amountPoints} Points to {$student->name} (ID {$student->id}).";
        };
    }

    private function lockWorkspaceUser(int $id, PendingAiAction $action, string $message): User
    {
        $user = User::query()
            ->whereKey($id)
            ->where(function ($query) use ($action) {
                $query->whereHas('workspaces', fn ($q) => $q->whereKey($action->workspace_id))
                    ->orWhereHas('sections', fn ($q) => $q->where('workspace_id', $action->workspace_id))
                    ->orWhere('current_workspace_id', $action->workspace_id);
            })
            ->lockForUpdate()
            ->first();

        if (! $user && $action->user?->isSuperAdmin()) {
            $user = User::query()->whereKey($id)->lockForUpdate()->first();
        }

        if (! $user) {
            throw new PendingAiActionException($message);
        }

        return $user;
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

    /** @return Closure(): string */
    private function prepareExportReport(PendingAiAction $action): Closure
    {
        $payload = $action->payload;
        $actorId = (int) $action->user_id;
        $workspaceId = (int) $action->workspace_id;

        return function () use ($payload, $actorId, $workspaceId): string {
            $export = app(ReportExportService::class)->export($payload, $actorId, $workspaceId);

            return 'Report exported successfully: '.$export['filename'].'. Download: '.$export['url'].' (expires '.$export['expires_at'].').';
        };
    }

    /** @return Closure(): string */
    private function prepareManageMaintenance(PendingAiAction $action): Closure
    {
        $payload = $action->payload ?? [];

        return function () use ($payload): string {
            PlatformMaintenance::save($payload);

            $status = ! empty($payload['maintenance_enabled']) ? 'enabled' : 'disabled';

            return "Platform maintenance mode has been successfully {$status}.";
        };
    }

    /** @return Closure(): string */
    private function prepareManageExamBlocks(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Exam $exam */
            $exam = $this->lockWorkspaceRecord(Exam::class, (int) $payload['exam_id'], $action, 'The exam no longer exists in this workspace.');
            $this->assertUnchanged($exam, $payload['expected_updated_at'] ?? null);

            $ids = collect($payload['blocked_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            app(ExamBlockService::class)->sync($exam, $ids);

            return "Exam \"{$exam->title}\" (ID {$exam->id}) blocked-student list updated: ".count($ids).' student(s) blocked.';
        };
    }

    /** @return Closure(): string */
    private function prepareSendNotification(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $ids = collect($payload['student_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $title = (string) $payload['title'];
            $message = (string) $payload['message'];
            $href = (string) ($payload['href'] ?? '/dashboard');
            $meta = 'Sent by '.($action->user?->name ?? 'an administrator');

            $sent = 0;
            foreach ($ids as $id) {
                $student = $this->lockWorkspaceUser($id, $action, 'A student no longer belongs to this workspace.');

                $student->notify(new StudentActivityNotification([
                    'type' => 'announcement',
                    'icon' => 'megaphone',
                    'title' => $title,
                    'message' => $message,
                    'meta' => $meta,
                    'href' => $href,
                ]));

                $sent++;
            }

            return "Notification \"{$title}\" sent to {$sent} student(s).";
        };
    }

    /** @return Closure(): string */
    private function prepareReplySupportTicket(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var SupportTicket $ticket */
            $ticket = $this->lockWorkspaceRecord(SupportTicket::class, (int) $payload['ticket_id'], $action, 'The support ticket no longer exists in this workspace.');
            $this->assertUnchanged($ticket, $payload['expected_updated_at'] ?? null);

            $admin = $action->user;
            if (! $admin) {
                throw new PendingAiActionException('The approving administrator no longer exists.');
            }

            app(SupportReplyService::class)->createAdminReply($ticket, $admin, (string) $payload['message']);

            $statusNote = '';
            $newStatus = $payload['new_status'] ?? null;
            if ($newStatus) {
                if (in_array($newStatus, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
                    $ticket->markResolved($admin, $newStatus);
                } else {
                    $ticket->update(['status' => $newStatus]);
                }

                $statusNote = ' Status set to '.(SupportTicket::STATUSES[$newStatus] ?? $newStatus).'.';
            }

            return "Replied to support ticket \"{$ticket->subject}\" (ID {$ticket->id}); the student was notified.{$statusNote}";
        };
    }

    /** @return Closure(): string */
    private function prepareSeasonAdmin(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            if (($payload['mode'] ?? '') === 'create') {
                $attributes = $payload['attributes'] ?? [];

                $season = Season::query()->create([
                    'workspace_id' => $action->workspace_id,
                    'admin_id' => $action->user_id,
                    'name' => (string) $attributes['name'],
                    'start_date' => Carbon::parse($attributes['start_date']),
                    'end_date' => ! empty($attributes['end_date']) ? Carbon::parse($attributes['end_date']) : null,
                    'is_active' => (bool) ($attributes['is_active'] ?? false),
                    'show_countdown_on_welcome' => (bool) ($attributes['show_countdown_on_welcome'] ?? false),
                ]);

                return "Season \"{$season->name}\" (ID {$season->id}) created.".($season->is_active ? ' It is now the active season for this workspace.' : '');
            }

            /** @var Season $season */
            $season = $this->lockWorkspaceRecord(Season::class, (int) $payload['season_id'], $action, 'The season no longer exists in this workspace.');
            $this->assertUnchanged($season, $payload['expected_updated_at'] ?? null);

            $changes = $payload['changes'] ?? [];

            if (array_key_exists('name', $changes)) {
                $season->name = (string) $changes['name'];
            }
            if (array_key_exists('start_date', $changes)) {
                $season->start_date = Carbon::parse($changes['start_date']);
            }
            if (array_key_exists('end_date', $changes)) {
                $season->end_date = $changes['end_date'] !== null ? Carbon::parse($changes['end_date']) : null;
            }
            if (array_key_exists('is_active', $changes)) {
                $season->is_active = (bool) $changes['is_active'];
            }
            if (array_key_exists('show_countdown_on_welcome', $changes)) {
                $season->show_countdown_on_welcome = (bool) $changes['show_countdown_on_welcome'];
            }

            if ($season->start_date && $season->end_date && $season->end_date->lt($season->start_date)) {
                throw new PendingAiActionException('The season end date cannot be before its start date.');
            }

            $season->save();

            $suffix = $season->is_active ? ' The season is now active; other seasons in this workspace were deactivated.' : '';

            return "Season \"{$season->name}\" (ID {$season->id}) updated.{$suffix}";
        };
    }

    /** @return Closure(): string */
    private function prepareManageBadgesRewards(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            $isBadge = ($payload['entity'] ?? '') === 'badge';
            $class = $isBadge ? Badge::class : Reward::class;
            $label = $isBadge ? 'Badge' : 'Reward';
            $operation = $payload['operation'] ?? '';

            if ($operation === 'create') {
                $record = $class::query()->create([
                    'workspace_id' => $action->workspace_id,
                    'admin_id' => $action->user_id,
                    ...($payload['attributes'] ?? []),
                ]);

                return "{$label} \"{$record->name}\" (ID {$record->id}) created.";
            }

            /** @var Badge|Reward $record */
            $record = $this->lockWorkspaceRecord($class, (int) $payload['record_id'], $action, "The {$label} no longer exists in this workspace.");
            $this->assertUnchanged($record, $payload['expected_updated_at'] ?? null);

            if ($operation === 'delete') {
                $name = $record->name;
                $id = $record->id;
                $record->users()->detach();
                $record->delete();

                return "{$label} \"{$name}\" (ID {$id}) was deleted.";
            }

            $allowed = $isBadge
                ? ['name', 'description', 'required_level', 'icon_url']
                : ['name', 'description', 'points_cost'];

            foreach (($payload['changes'] ?? []) as $field => $value) {
                if (in_array($field, $allowed, true)) {
                    $record->{$field} = $value;
                }
            }
            $record->save();

            return "{$label} \"{$record->name}\" (ID {$record->id}) updated.";
        };
    }

    /** @return Closure(): string */
    private function prepareManageLesson(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            if (($payload['operation'] ?? '') === 'create') {
                $attributes = $payload['attributes'] ?? [];

                /** @var CourseModule $module */
                $module = $this->lockWorkspaceRecord(CourseModule::class, (int) $attributes['course_module_id'], $action, 'The course module no longer exists in this workspace.');

                $lesson = Lesson::query()->create([
                    'workspace_id' => $action->workspace_id,
                    'admin_id' => $action->user_id,
                    'course_module_id' => $module->id,
                    'title' => (string) $attributes['title'],
                    'content' => $attributes['content'] ?? null,
                    'video_url' => $attributes['video_url'] ?? null,
                    'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                ]);

                return "Lesson \"{$lesson->title}\" (ID {$lesson->id}) created in module \"{$module->title}\".";
            }

            /** @var Lesson $lesson */
            $lesson = $this->lockWorkspaceRecord(Lesson::class, (int) $payload['lesson_id'], $action, 'The lesson no longer exists in this workspace.');
            $this->assertUnchanged($lesson, $payload['expected_updated_at'] ?? null);

            $changes = $payload['changes'] ?? [];

            if (array_key_exists('course_module_id', $changes)) {
                /** @var CourseModule $module */
                $module = $this->lockWorkspaceRecord(CourseModule::class, (int) $changes['course_module_id'], $action, 'The target course module no longer exists in this workspace.');
                $lesson->course_module_id = $module->id;
            }

            foreach (['title', 'content', 'video_url', 'sort_order'] as $field) {
                if (array_key_exists($field, $changes)) {
                    $lesson->{$field} = $changes[$field];
                }
            }

            $lesson->save();

            return "Lesson \"{$lesson->title}\" (ID {$lesson->id}) updated.";
        };
    }

    /** @return Closure(): string */
    private function prepareManageLessonQuiz(PendingAiAction $action): Closure
    {
        $payload = $action->payload;

        return function () use ($action, $payload): string {
            /** @var Lesson $lesson */
            $lesson = $this->lockWorkspaceRecord(Lesson::class, (int) $payload['lesson_id'], $action, 'The lesson no longer exists in this workspace.');

            $quiz = LessonQuiz::query()->where('lesson_id', $lesson->id)->first();

            if ($quiz) {
                $this->assertUnchanged($quiz, $payload['expected_updated_at'] ?? null);
            }

            $quiz ??= new LessonQuiz(['lesson_id' => $lesson->id]);
            $quiz->questions = $payload['questions'];
            $quiz->pass_score = (int) $payload['pass_score'];
            $quiz->allowed_attempts = (int) $payload['allowed_attempts'];
            $quiz->save();

            return 'Quiz for lesson "'.$lesson->title."\" (ID {$lesson->id}) saved with ".count($payload['questions']).' question(s).';
        };
    }
}
