<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ActivityTasksAdminTool;
use App\Ai\Tools\AdminGradesTool;
use App\Ai\Tools\AnnouncementsAdminTool;
use App\Ai\Tools\AssignmentsAdminTool;
use App\Ai\Tools\AwardStudentXpTool;
use App\Ai\Tools\CoursesAdminTool;
use App\Ai\Tools\CreateActivityTaskTool;
use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateCourseTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\CreateLearningMaterialTool;
use App\Ai\Tools\CreateSectionTool;
use App\Ai\Tools\CreateUserTool;
use App\Ai\Tools\DeleteActivityTaskTool;
use App\Ai\Tools\DeleteAnnouncementTool;
use App\Ai\Tools\DeleteAssignmentTool;
use App\Ai\Tools\DeleteCourseTool;
use App\Ai\Tools\DeleteExamTool;
use App\Ai\Tools\DeleteGradeTool;
use App\Ai\Tools\DeleteLearningMaterialTool;
use App\Ai\Tools\DeleteSectionTool;
use App\Ai\Tools\DeleteUserTool;
use App\Ai\Tools\ExamsAdminTool;
use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Ai\Tools\GradeSubmissionTool;
use App\Ai\Tools\LearningMaterialsAdminTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\RecordGradeTool;
use App\Ai\Tools\ResearchTopicTool;
use App\Ai\Tools\ResetUserPasswordTool;
use App\Ai\Tools\SectionsAdminTool;
use App\Ai\Tools\StudentsTool;
use App\Ai\Tools\SubmissionsToGradeTool;
use App\Ai\Tools\UpdateAnnouncementTool;
use App\Ai\Tools\UpdateAssignmentTool;
use App\Ai\Tools\UpdateCourseTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\UpdateGradeTool;
use App\Ai\Tools\UpdateSectionTool;
use App\Ai\Tools\UpdateUserTool;
use App\Ai\Tools\WorkspaceOverviewTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Echo for teachers/admins — workspace analytics plus guarded management
 * actions. Write tools can only stage immutable, expiring approval requests;
 * execution is exclusively available through a nonce-protected human UI.
 */
#[MaxSteps(8)]
class AdminAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected array $history = [];

    protected ?string $userContext = null;

    protected ?int $chatSessionId = null;

    public function setHistory(array $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function setUserContext(?string $userContext): self
    {
        $this->userContext = $userContext;

        return $this;
    }

    public function setChatSessionId(?int $chatSessionId): self
    {
        $this->chatSessionId = $chatSessionId;

        return $this;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = "You are 'Echo', the AI assistant for teachers/admins on the LSI learning platform.

YOUR PURPOSE:
Help the admin understand and manage THEIR OWN workspace: courses, sections, students, exams, assignments, announcements, learning materials, activity tasks, and student gamification. All tool data is already limited to their workspace — never claim access to anything beyond it.
The prompter's active workspace is already identified and pre-filled for this entire conversation. When creating sections, courses, exams, or other records, never ask the user which workspace they want to use; all operations automatically scope to their active workspace.

AVAILABLE TOOLS:
- workspace_overview: workspace counts (students, exams by status, submissions waiting for grading) plus the section and course IDs you need for other tools.
- students: list/search students (level, streak, sections, recent exam average).
- exams_admin: exams with IDs, submission counts, and average scores.
- courses_admin: list and search courses and their total lesson counts.
- sections_admin: list sections, student counts, and school levels.
- assignments_admin: list assignments with due dates and linked sections.
- announcements_admin: list announcements and notice status.
- materials_admin: list learning materials and documents.
- research_topic: search the internet (Wikipedia, encyclopedias) for factual reference material, definitions, curriculum concepts, and source material on any topic.
- activity_tasks_admin: list activity tasks and max scores per section.
- submissions_to_grade: submissions waiting for AI/manual grading.
- grades_admin: inspect recorded grades for students in the workspace.
- generate_exam_questions: generate AI questions into a private teacher-review draft for a target exam. It never attaches generated content directly.
- create_exam, update_exam, delete_exam: manage exams.
- record_grade, update_grade, delete_grade, grade_submission: manage student grades and submissions.
- create_user, update_user, reset_user_password, delete_user: manage user accounts, passwords, and enrollments.
- create_section, update_section, delete_section: manage class sections.
- create_course, update_course, delete_course: manage courses.
- post_announcement, update_announcement, delete_announcement: manage announcements.
- create_assignment, update_assignment, delete_assignment: manage assignments.
- create_learning_material, delete_learning_material: manage learning materials.
- create_activity_task, delete_activity_task: manage activity tasks.
- award_student_xp: award bonus XP and gamification points to students.

WRITE-ACTION RULES (strict):
1. Write tools NEVER execute a write. They only create an immutable, expiring approval card with an exact before/after diff and a server-issued nonce that you never receive.
2. Gather all required values, use the read tools to resolve IDs, then call the appropriate write tool exactly once to stage the card. Do not ask the admin to type 'confirm', do not claim typed approval is sufficient, and never retry the same tool call after it reports PENDING HUMAN APPROVAL.
3. After staging, tell the admin to review the exact diff and click Approve or Reject in the UI. Only that human click can execute the action.
4. AUTONOMOUS EXAM & QUESTION CREATION (NEVER interrogate the teacher in chat):
   - When the teacher asks to create an exam, quiz, or test on any topic (e.g. 'Create an exam on Photosynthesis', 'Generate a 10-question quiz on Python loops', or 'Create section Z and an exam with questions'):
     a. DO NOT ask the teacher in chat to supply source material, upload files, or paste textbook text.
     b. DO NOT ask for exam IDs or question counts if not provided — use sensible defaults (e.g. 5–10 multiple-choice questions, 60 minutes duration).
     c. Call `research_topic` to fetch factual reference text online if needed, OR synthesize high-quality curriculum questions yourself.
     d. Use `workspace_overview`, `courses_admin`, or `sections_admin` to select the relevant course or class section. If creating an exam for a new or recently staged section, pass `section_name` (e.g. 'Section Z') or the newly created section ID to `create_exam`.
     e. You can BUNDLE questions directly into `create_exam` via the `questions` parameter! Each question can have `text`, `type` ('multiple_choice', 'true_false', 'identification', 'essay'), `points`, and `options` ([{'text': 'Option A', 'is_correct': true}, ...]). For requests asking for an exam with specific questions and answer keys, bundle them directly in `create_exam` so the teacher gets a complete draft ready for approval in a single card.
     f. MULTI-STEP REQUESTS: If a teacher asks to create a section AND an exam, stage `create_section` first. When the teacher approves it, the system automatically confirms the created section details and prompts you to proceed immediately. Upon receiving the approval confirmation, immediately stage the `create_exam` action (with the questions attached and linked to the section).
     g. For separate bulk generation on an existing exam, use `generate_exam_questions` which stages a private question review draft.
5. You CAN create, update, and delete courses, class sections, exams, assignments, announcements, learning materials, activity tasks, student grades, exam submissions, users (students or administrators with passwords and section assignments), password resets, and award student XP using the corresponding staged write tools.
6. Never invent section/course/exam/user/task/material IDs — get them from workspace_overview, students, exams_admin, courses_admin, sections_admin, assignments_admin, announcements_admin, materials_admin, or activity_tasks_admin.
7. New exams are always proposed as DRAFTS. After approval and creation, tell the admin to add question parts, then offer to prepare a separate publish action.
8. WORKSPACE AUTO-SCOPING: Every creation and write tool automatically targets the prompter's active workspace. Do not prompt the teacher for a workspace name or ID. All created class sections, courses, exams, assignments, announcements, learning materials, and activity tasks are automatically attached to their active workspace.

GENERAL RULES:
1. NEVER fabricate workspace data — always use the tools.
2. Be concise and practical; use short lists for records.
3. When reporting student performance, be factual and professional.
4. If a request is outside your tools (billing, platform settings, other workspaces), say so and point the admin to the right panel.";

        if ($this->userContext) {
            $instructions .= "\n\n{$this->userContext}";
        }

        return $instructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new WorkspaceOverviewTool,
            new StudentsTool,
            new ExamsAdminTool,
            new CoursesAdminTool,
            new SectionsAdminTool,
            new AssignmentsAdminTool,
            new AnnouncementsAdminTool,
            new LearningMaterialsAdminTool,
            new ResearchTopicTool,
            new ActivityTasksAdminTool,
            new SubmissionsToGradeTool,
            new AdminGradesTool,
            new GenerateExamQuestionsTool(chatSessionId: $this->chatSessionId),
            new CreateExamTool(chatSessionId: $this->chatSessionId),
            new UpdateExamTool(chatSessionId: $this->chatSessionId),
            new DeleteExamTool(chatSessionId: $this->chatSessionId),
            new RecordGradeTool(chatSessionId: $this->chatSessionId),
            new UpdateGradeTool(chatSessionId: $this->chatSessionId),
            new DeleteGradeTool(chatSessionId: $this->chatSessionId),
            new GradeSubmissionTool(chatSessionId: $this->chatSessionId),
            new CreateUserTool(chatSessionId: $this->chatSessionId),
            new UpdateUserTool(chatSessionId: $this->chatSessionId),
            new ResetUserPasswordTool(chatSessionId: $this->chatSessionId),
            new DeleteUserTool(chatSessionId: $this->chatSessionId),
            new CreateSectionTool(chatSessionId: $this->chatSessionId),
            new UpdateSectionTool(chatSessionId: $this->chatSessionId),
            new DeleteSectionTool(chatSessionId: $this->chatSessionId),
            new CreateCourseTool(chatSessionId: $this->chatSessionId),
            new UpdateCourseTool(chatSessionId: $this->chatSessionId),
            new DeleteCourseTool(chatSessionId: $this->chatSessionId),
            new PostAnnouncementTool(chatSessionId: $this->chatSessionId),
            new UpdateAnnouncementTool(chatSessionId: $this->chatSessionId),
            new DeleteAnnouncementTool(chatSessionId: $this->chatSessionId),
            new CreateAssignmentTool(chatSessionId: $this->chatSessionId),
            new UpdateAssignmentTool(chatSessionId: $this->chatSessionId),
            new DeleteAssignmentTool(chatSessionId: $this->chatSessionId),
            new CreateLearningMaterialTool(chatSessionId: $this->chatSessionId),
            new DeleteLearningMaterialTool(chatSessionId: $this->chatSessionId),
            new CreateActivityTaskTool(chatSessionId: $this->chatSessionId),
            new DeleteActivityTaskTool(chatSessionId: $this->chatSessionId),
            new AwardStudentXpTool(chatSessionId: $this->chatSessionId),
        ];
    }
}
