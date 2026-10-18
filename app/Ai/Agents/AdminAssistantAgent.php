<?php

namespace App\Ai\Agents;

use App\Ai\Skills\AdminAssistantSkill;
use App\Ai\Support\AdminAssistantIntent;
use App\Ai\Tools\ActivityTasksAdminTool;
use App\Ai\Tools\AdminGradesTool;
use App\Ai\Tools\AiActionLogTool;
use App\Ai\Tools\AnnouncementsAdminTool;
use App\Ai\Tools\AssignmentsAdminTool;
use App\Ai\Tools\AtRiskStudentsTool;
use App\Ai\Tools\AwardStudentXpTool;
use App\Ai\Tools\CoursesAdminTool;
use App\Ai\Tools\CreateActivityTaskTool;
use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateCourseTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\CreateLearningMaterialTool;
use App\Ai\Tools\CreateSectionTool;
use App\Ai\Tools\CreateUserTool;
use App\Ai\Tools\DailyReportTool;
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
use App\Ai\Tools\ExportReportTool;
use App\Ai\Tools\FailedJobsTool;
use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Ai\Tools\GradeSubmissionTool;
use App\Ai\Tools\LeaderboardTool;
use App\Ai\Tools\LearningMaterialsAdminTool;
use App\Ai\Tools\ListWorkspacesTool;
use App\Ai\Tools\ManageBadgesRewardsTool;
use App\Ai\Tools\ManageExamBlocksTool;
use App\Ai\Tools\ManageLessonQuizTool;
use App\Ai\Tools\ManageLessonTool;
use App\Ai\Tools\ManageMaintenanceTool;
use App\Ai\Tools\PlatformCountsTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\RecordActivityTaskScoreTool;
use App\Ai\Tools\RecordGradeTool;
use App\Ai\Tools\ReplySupportTicketTool;
use App\Ai\Tools\ResearchTopicTool;
use App\Ai\Tools\ResetUserPasswordTool;
use App\Ai\Tools\SeasonAdminTool;
use App\Ai\Tools\SectionPerformanceTool;
use App\Ai\Tools\SectionsAdminTool;
use App\Ai\Tools\SendNotificationTool;
use App\Ai\Tools\StudentsTool;
use App\Ai\Tools\SubmissionsToGradeTool;
use App\Ai\Tools\SupportTicketsTool;
use App\Ai\Tools\UpdateActivityTaskTool;
use App\Ai\Tools\UpdateAnnouncementTool;
use App\Ai\Tools\UpdateAssignmentTool;
use App\Ai\Tools\UpdateCourseTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\UpdateGradeTool;
use App\Ai\Tools\UpdateLearningMaterialTool;
use App\Ai\Tools\UpdateSectionTool;
use App\Ai\Tools\UpdateUserTool;
use App\Ai\Tools\UserActivityTool;
use App\Ai\Tools\WorkspaceOverviewTool;
use App\Support\PlatformKnowledge;
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
 * actions. Write tools only stage approval requests; execution is exclusively
 * available through a nonce-protected human UI.
 *
 * MaxSteps is 12 so multi-step intents (section + exam + questions) can finish
 * when write tools are scoped to matching groups rather than the full catalog.
 */
#[MaxSteps(12)]
class AdminAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected array $history = [];

    protected ?string $userContext = null;

    protected ?int $chatSessionId = null;

    /** @var list<string>|null */
    protected ?array $intentGroups = null;

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
     * @param  list<string>|null  $groups
     */
    public function setIntentGroups(?array $groups): self
    {
        $this->intentGroups = $groups;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $instructions = "You are 'Echo', the AI assistant for teachers/admins on the LSI learning platform.

ROLE BOUNDARIES:
- Super Administrators (Is Super Admin: YES): may use platform-wide tools when those tools exist (manage_maintenance, daily_report, list_workspaces, platform_counts, failed_jobs). Workspace tools still default to the active workspace.
- Workspace Administrators (Is Super Admin: NO): only the active workspace. Decline platform maintenance, global settings, and other-workspace data.
- Platform-wide answers only when a platform-wide tool was used. Never claim all-workspace data from a workspace-scoped tool.
- The active workspace is already resolved. Never ask which workspace to use for ordinary writes.

WRITE-APPROVAL RULES (strict):
1. Write tools NEVER execute a write. They only stage an immutable, expiring approval card with an exact diff and a server nonce you never receive.
2. NEVER claim an approval card exists, is ready, or was staged unless a write tool actually ran this turn and returned PENDING HUMAN APPROVAL with an action_id.
3. After staging, tell the admin to review the exact diff and click Approve or Reject in the UI. Typed 'confirm' is never approval. Do not retry the same tool after pending.
4. Never invent student, section, course, exam, submission, or grade IDs — resolve them with read tools first.
5. A message that is only a person's name is a student search: call `students` immediately with only that name as `search`. If several match, list options; never pick silently.

RESPONSE CONTRACT:
1. Outcome — one sentence: found, staged, declined, or blocked.
2. Facts — short list from tool fields only (include action_id and expires_at when a write was staged).
3. Next step — approve the card, choose among options, or the one follow-up tool you can run.
No greeting, no restating the question, no policy lecture. Past tense only for completed reads; future or pending for writes.

READ TOOLS (always available): workspace_overview, students, user_activity, at_risk_students, section_performance, leaderboard, ai_action_log, support_tickets, exams_admin, courses_admin, sections_admin, assignments_admin, announcements_admin, materials_admin, research_topic, activity_tasks_admin, submissions_to_grade, grades_admin.
PLATFORM TOOLS (Super Admin): manage_maintenance, daily_report, list_workspaces, platform_counts, failed_jobs.
WRITE TOOLS are mounted by intent for this turn; use only tools present in the tool list.
Export via export_report stages only — never invent a filename or download URL until approval returns one.";

        if ($this->userContext) {
            $instructions .= "\n\n{$this->userContext}";
        }

        $instructions .= "\n\n".PlatformKnowledge::adminNotes();
        $instructions .= "\n\nRUNTIME SKILL:\n".AdminAssistantSkill::instructions();

        return $instructions;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        $sid = $this->chatSessionId;
        $groups = $this->intentGroups ?? [AdminAssistantIntent::ALL];
        $all = in_array(AdminAssistantIntent::ALL, $groups, true);

        $tools = [
            // Always-on reads
            new WorkspaceOverviewTool,
            new StudentsTool,
            new UserActivityTool,
            new AtRiskStudentsTool,
            new SectionPerformanceTool,
            new LeaderboardTool,
            new AiActionLogTool,
            new SupportTicketsTool,
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
            // Platform reads (tool itself enforces Super Admin)
            new DailyReportTool,
            new ListWorkspacesTool,
            new PlatformCountsTool,
            new FailedJobsTool,
            new ManageMaintenanceTool(chatSessionId: $sid),
            new ExportReportTool(chatSessionId: $sid),
        ];

        if ($all || in_array(AdminAssistantIntent::EXAMS, $groups, true)) {
            array_push(
                $tools,
                new GenerateExamQuestionsTool(chatSessionId: $sid),
                new CreateExamTool(chatSessionId: $sid),
                new UpdateExamTool(chatSessionId: $sid),
                new DeleteExamTool(chatSessionId: $sid),
                new ManageExamBlocksTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::GRADES, $groups, true)) {
            array_push(
                $tools,
                new RecordGradeTool(chatSessionId: $sid),
                new UpdateGradeTool(chatSessionId: $sid),
                new DeleteGradeTool(chatSessionId: $sid),
                new GradeSubmissionTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::USERS, $groups, true)) {
            array_push(
                $tools,
                new CreateUserTool(chatSessionId: $sid),
                new UpdateUserTool(chatSessionId: $sid),
                new ResetUserPasswordTool(chatSessionId: $sid),
                new DeleteUserTool(chatSessionId: $sid),
                new SendNotificationTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::SECTIONS, $groups, true)) {
            array_push(
                $tools,
                new CreateSectionTool(chatSessionId: $sid),
                new UpdateSectionTool(chatSessionId: $sid),
                new DeleteSectionTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::COURSES, $groups, true)) {
            array_push(
                $tools,
                new CreateCourseTool(chatSessionId: $sid),
                new UpdateCourseTool(chatSessionId: $sid),
                new DeleteCourseTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::ANNOUNCEMENTS, $groups, true)) {
            array_push(
                $tools,
                new PostAnnouncementTool(chatSessionId: $sid),
                new UpdateAnnouncementTool(chatSessionId: $sid),
                new DeleteAnnouncementTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::ASSIGNMENTS, $groups, true)) {
            array_push(
                $tools,
                new CreateAssignmentTool(chatSessionId: $sid),
                new UpdateAssignmentTool(chatSessionId: $sid),
                new DeleteAssignmentTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::MATERIALS, $groups, true)) {
            array_push(
                $tools,
                new CreateLearningMaterialTool(chatSessionId: $sid),
                new UpdateLearningMaterialTool(chatSessionId: $sid),
                new DeleteLearningMaterialTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::ACTIVITY, $groups, true)) {
            array_push(
                $tools,
                new CreateActivityTaskTool(chatSessionId: $sid),
                new UpdateActivityTaskTool(chatSessionId: $sid),
                new DeleteActivityTaskTool(chatSessionId: $sid),
                new RecordActivityTaskScoreTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::GAMIFICATION, $groups, true)) {
            array_push(
                $tools,
                new AwardStudentXpTool(chatSessionId: $sid),
                new SeasonAdminTool(chatSessionId: $sid),
                new ManageBadgesRewardsTool(chatSessionId: $sid),
            );
        }

        if ($all || in_array(AdminAssistantIntent::SUPPORT, $groups, true)) {
            $tools[] = new ReplySupportTicketTool(chatSessionId: $sid);
        }

        if ($all || in_array(AdminAssistantIntent::LESSONS, $groups, true)) {
            array_push(
                $tools,
                new ManageLessonTool(chatSessionId: $sid),
                new ManageLessonQuizTool(chatSessionId: $sid),
            );
        }

        return $tools;
    }
}
