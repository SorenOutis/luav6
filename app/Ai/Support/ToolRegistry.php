<?php

declare(strict_types=1);

namespace App\Ai\Support;

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
use App\Ai\Tools\RememberBotMemory;
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
use Laravel\Ai\Contracts\Tool;

/**
 * Stable key -> tool class map shared by Echo and database-driven bots.
 *
 * This is the single source of truth for tool mounting, write detection, and
 * intent routing. Never maintain a parallel write-key list.
 */
final class ToolRegistry
{
    /**
     * @var array<string, array{
     *     class: class-string<Tool>,
     *     write: bool,
     *     intents: list<string>,
     * }>
     */
    private const MAP = [
        'workspace_overview' => ['class' => WorkspaceOverviewTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'students' => ['class' => StudentsTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'user_activity' => ['class' => UserActivityTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'at_risk_students' => ['class' => AtRiskStudentsTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'section_performance' => ['class' => SectionPerformanceTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'leaderboard' => ['class' => LeaderboardTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'ai_action_log' => ['class' => AiActionLogTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'support_tickets' => ['class' => SupportTicketsTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'exams_admin' => ['class' => ExamsAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'courses_admin' => ['class' => CoursesAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'sections_admin' => ['class' => SectionsAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'assignments_admin' => ['class' => AssignmentsAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'announcements_admin' => ['class' => AnnouncementsAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'materials_admin' => ['class' => LearningMaterialsAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'research_topic' => ['class' => ResearchTopicTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'activity_tasks_admin' => ['class' => ActivityTasksAdminTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'submissions_to_grade' => ['class' => SubmissionsToGradeTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'grades_admin' => ['class' => AdminGradesTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'daily_report' => ['class' => DailyReportTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'list_workspaces' => ['class' => ListWorkspacesTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'platform_counts' => ['class' => PlatformCountsTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'failed_jobs' => ['class' => FailedJobsTool::class, 'write' => false, 'intents' => [AdminAssistantIntent::ALL]],
        'manage_maintenance' => ['class' => ManageMaintenanceTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::PLATFORM]],
        'export_report' => ['class' => ExportReportTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXPORT]],
        'generate_exam_questions' => ['class' => GenerateExamQuestionsTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXAMS]],
        'create_exam' => ['class' => CreateExamTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXAMS]],
        'update_exam' => ['class' => UpdateExamTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXAMS]],
        'delete_exam' => ['class' => DeleteExamTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXAMS]],
        'manage_exam_blocks' => ['class' => ManageExamBlocksTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::EXAMS]],
        'record_grade' => ['class' => RecordGradeTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GRADES]],
        'update_grade' => ['class' => UpdateGradeTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GRADES]],
        'delete_grade' => ['class' => DeleteGradeTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GRADES]],
        'grade_submission' => ['class' => GradeSubmissionTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GRADES]],
        'create_user' => ['class' => CreateUserTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::USERS]],
        'update_user' => ['class' => UpdateUserTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::USERS]],
        'reset_user_password' => ['class' => ResetUserPasswordTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::USERS]],
        'delete_user' => ['class' => DeleteUserTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::USERS]],
        'send_notification' => ['class' => SendNotificationTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::USERS]],
        'create_section' => ['class' => CreateSectionTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::SECTIONS]],
        'update_section' => ['class' => UpdateSectionTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::SECTIONS]],
        'delete_section' => ['class' => DeleteSectionTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::SECTIONS]],
        'create_course' => ['class' => CreateCourseTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::COURSES]],
        'update_course' => ['class' => UpdateCourseTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::COURSES]],
        'delete_course' => ['class' => DeleteCourseTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::COURSES]],
        'post_announcement' => ['class' => PostAnnouncementTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ANNOUNCEMENTS]],
        'update_announcement' => ['class' => UpdateAnnouncementTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ANNOUNCEMENTS]],
        'delete_announcement' => ['class' => DeleteAnnouncementTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ANNOUNCEMENTS]],
        'create_assignment' => ['class' => CreateAssignmentTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ASSIGNMENTS]],
        'update_assignment' => ['class' => UpdateAssignmentTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ASSIGNMENTS]],
        'delete_assignment' => ['class' => DeleteAssignmentTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ASSIGNMENTS]],
        'create_learning_material' => ['class' => CreateLearningMaterialTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::MATERIALS]],
        'update_learning_material' => ['class' => UpdateLearningMaterialTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::MATERIALS]],
        'delete_learning_material' => ['class' => DeleteLearningMaterialTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::MATERIALS]],
        'create_activity_task' => ['class' => CreateActivityTaskTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ACTIVITY]],
        'update_activity_task' => ['class' => UpdateActivityTaskTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ACTIVITY]],
        'delete_activity_task' => ['class' => DeleteActivityTaskTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ACTIVITY]],
        'record_activity_task_score' => ['class' => RecordActivityTaskScoreTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::ACTIVITY]],
        'award_student_xp' => ['class' => AwardStudentXpTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GAMIFICATION]],
        'season_admin' => ['class' => SeasonAdminTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GAMIFICATION]],
        'manage_badges_rewards' => ['class' => ManageBadgesRewardsTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::GAMIFICATION]],
        'reply_support_ticket' => ['class' => ReplySupportTicketTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::SUPPORT]],
        'manage_lesson' => ['class' => ManageLessonTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::LESSONS]],
        'manage_lesson_quiz' => ['class' => ManageLessonQuizTool::class, 'write' => true, 'intents' => [AdminAssistantIntent::LESSONS]],
        // Bot-only capability: Echo has no bot identity, so it must never mount this.
        'remember_bot_memory' => ['class' => RememberBotMemory::class, 'write' => true, 'intents' => []],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    /** @return class-string<Tool>|null */
    public static function classFor(string $key): ?string
    {
        return self::MAP[$key]['class'] ?? null;
    }

    public static function isWrite(string $key): bool
    {
        return (bool) (self::MAP[$key]['write'] ?? false);
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::MAP[$key]);
    }

    /** @return list<string> */
    public static function intentsFor(string $key): array
    {
        return self::MAP[$key]['intents'] ?? [];
    }

    /**
     * Keys mounted for an intent, in registry order. Read tools carry ALL and
     * therefore stay mounted for every turn, matching Echo's existing surface.
     *
     * @return list<string>
     */
    public static function keysForIntent(string $intent): array
    {
        if ($intent === AdminAssistantIntent::ALL) {
            return array_keys(array_filter(
                self::MAP,
                fn (array $entry): bool => $entry['intents'] !== [],
            ));
        }

        return array_keys(array_filter(
            self::MAP,
            fn (array $entry): bool => in_array(AdminAssistantIntent::ALL, $entry['intents'], true)
                || in_array($intent, $entry['intents'], true),
        ));
    }

    /** @return list<string> */
    public static function readKeys(): array
    {
        return array_keys(array_filter(self::MAP, fn (array $entry): bool => ! $entry['write']));
    }

    /** @return list<string> */
    public static function writeKeys(): array
    {
        return array_keys(array_filter(self::MAP, fn (array $entry): bool => $entry['write']));
    }

    public static function make(string $key, ?int $chatSessionId = null, ?int $botId = null, ?int $handoffId = null): ?Tool
    {
        $class = self::classFor($key);

        if (! $class) {
            return null;
        }

        if (self::isWrite($key)) {
            return new $class(
                chatSessionId: $chatSessionId,
                botId: $botId,
                handoffId: $handoffId,
            );
        }

        return new $class;
    }

    /**
     * Instantiate an allowlist in registry order, ignoring unknown keys.
     *
     * @param  array<int, string>  $keys
     * @return array<int, Tool>
     */
    public static function makeMany(array $keys, ?int $chatSessionId = null, ?int $botId = null, ?int $handoffId = null): array
    {
        $allowed = array_fill_keys($keys, true);
        $tools = [];

        foreach (self::MAP as $key => $entry) {
            if (! isset($allowed[$key])) {
                continue;
            }

            $tools[] = self::make($key, $chatSessionId, $botId, $handoffId);
        }

        return array_values(array_filter($tools));
    }

    /**
     * Flat options for the bot allowlist UI. Read tools come first and write
     * tools last, so an admin must opt in to approval-gated capabilities
     * explicitly. Filament's CheckboxList does not support grouped options.
     *
     * @return array<string, string>
     */
    public static function options(bool $includeWrites = true): array
    {
        $options = [];

        foreach (self::MAP as $key => $entry) {
            if ($entry['write'] && ! $includeWrites) {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $key));
            $options[$key] = $entry['write'] ? $label.' (approval required)' : $label;
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $keys
     * @return list<string>
     */
    public static function unknownKeys(array $keys): array
    {
        return array_values(array_filter($keys, fn (string $key): bool => ! self::isKnown($key)));
    }
}
