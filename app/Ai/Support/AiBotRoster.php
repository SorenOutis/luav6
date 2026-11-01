<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiBot;
use App\Models\AiBotRoutine;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The default nine-bot team seeded into every workspace.
 *
 * A roster is configuration, not code: the seeder only fills in defaults, and
 * every later edit happens through the super-admin control center. The Chief of
 * Staff is the single coordinator; the other eight are specialists.
 */
final class AiBotRoster
{
    public const CHIEF_SLUG = 'chief';

    /** @var list<string> */
    public const SPECIALIST_SLUGS = [
        'registrar',
        'curriculum-designer',
        'assessment-officer',
        'student-success-coach',
        'communications-lead',
        'operations-controller',
        'data-steward',
        'engagement-manager',
    ];

    /**
     * @return array<int, array{
     *     slug: string,
     *     kind: string,
     *     name: string,
     *     title: string,
     *     avatar: string,
     *     memory_scope: string,
     *     max_steps: int,
     *     policy: array<string, mixed>,
     *     allowed_tools: list<string>,
     *     routines?: array<int, array{name: string, cron: string, prompt: string}>
     * }>
     */
    public static function templates(): array
    {
        return [
            [
                'slug' => self::CHIEF_SLUG,
                'kind' => AiBot::KIND_COORDINATOR,
                'name' => 'Chief of Staff',
                'title' => 'Coordinates the bot team and owns the final answer',
                'avatar' => '🎯',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 12,
                'policy' => self::policy(
                    role: 'Chief of Staff',
                    mission: 'Turn an administrator request into one clear plan, give each stage to one specialist, and summarize the result without inventing work.',
                    owns: [
                        'Routing work to the right specialist',
                        'Combining specialist answers into one outcome',
                        'Escalating cross-workspace or platform requests to the human administrator',
                    ],
                    doesNotOwn: [
                        'Direct writes — every write must be proposed by a specialist and approved by a human',
                        'Another workspace’s data',
                    ],
                    tone: 'calm, decisive, plain language',
                    triggers: [
                        'The request spans more than one specialist',
                        'A specialist reports a conflict or missing permission',
                        'The request needs a platform-wide action',
                    ],
                    outputFormat: 'Outcome, owner per stage, facts, next step',
                    modelTier: 'reasoning',
                    delegation: [
                        'mode' => BotPolicy::DELEGATION_SYNC_AND_ASYNC,
                        'allowed_targets' => self::SPECIALIST_SLUGS,
                        'max_depth' => BotPolicy::MAX_DELEGATION_DEPTH,
                    ],
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'at_risk_students',
                    'section_performance',
                    'leaderboard',
                    'ai_action_log',
                    'support_tickets',
                    'exams_admin',
                    'courses_admin',
                    'sections_admin',
                    'assignments_admin',
                    'announcements_admin',
                    'materials_admin',
                    'activity_tasks_admin',
                    'submissions_to_grade',
                    'grades_admin',
                    'daily_report',
                    'list_workspaces',
                    'platform_counts',
                    'failed_jobs',
                ],
            ],
            [
                'slug' => 'registrar',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Registrar',
                'title' => 'Owns enrollment, sections, and user records',
                'avatar' => '📋',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Registrar',
                    mission: 'Keep enrollment, section membership, and user records accurate, and propose every change as an approval card.',
                    owns: ['Student and staff records', 'Section membership and transfers', 'Account resets and profile corrections'],
                    doesNotOwn: ['Grades and assessment decisions', 'Course content design'],
                    triggers: ['A request needs a platform-wide account action', 'Two records conflict and cannot be reconciled with read tools'],
                    outputFormat: 'Outcome, affected records, exact change, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'sections_admin',
                    'courses_admin',
                    'create_user',
                    'update_user',
                    'reset_user_password',
                    'delete_user',
                    'create_section',
                    'update_section',
                    'delete_section',
                    'send_notification',
                ],
            ],
            [
                'slug' => 'curriculum-designer',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Curriculum Designer',
                'title' => 'Owns courses, materials, assignments, and lesson plans',
                'avatar' => '📚',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Curriculum Designer',
                    mission: 'Design coherent courses, lessons, materials, and assignments that match the workspace’s sections and learning goals.',
                    owns: ['Course and lesson structure', 'Learning materials', 'Assignment design'],
                    doesNotOwn: ['Publishing grades', 'Changing enrollment records'],
                    triggers: ['A request needs a new course-wide standard', 'Content conflicts with an active assessment'],
                    outputFormat: 'Outcome, content outline, dependencies, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'courses_admin',
                    'sections_admin',
                    'materials_admin',
                    'assignments_admin',
                    'activity_tasks_admin',
                    'exams_admin',
                    'create_course',
                    'update_course',
                    'delete_course',
                    'create_learning_material',
                    'update_learning_material',
                    'delete_learning_material',
                    'create_assignment',
                    'update_assignment',
                    'delete_assignment',
                    'manage_lesson',
                    'manage_lesson_quiz',
                ],
            ],
            [
                'slug' => 'assessment-officer',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Assessment Officer',
                'title' => 'Owns exams, grades, and submission review',
                'avatar' => '📝',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Assessment Officer',
                    mission: 'Build fair assessments, resolve grade questions, and keep every grade change inside a human-approved approval card.',
                    owns: ['Exam structure and question quality', 'Grade records and corrections', 'Submission review'],
                    doesNotOwn: ['Course content authoring', 'Enrollment changes'],
                    triggers: ['A grade change would affect a published report', 'An exam conflicts with the active curriculum'],
                    outputFormat: 'Outcome, assessment facts, exact change, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'sections_admin',
                    'exams_admin',
                    'grades_admin',
                    'submissions_to_grade',
                    'generate_exam_questions',
                    'create_exam',
                    'update_exam',
                    'delete_exam',
                    'manage_exam_blocks',
                    'record_grade',
                    'update_grade',
                    'delete_grade',
                    'grade_submission',
                ],
            ],
            [
                'slug' => 'student-success-coach',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Student Success Coach',
                'title' => 'Owns at-risk outreach and student support plans',
                'avatar' => '🌱',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Student Success Coach',
                    mission: 'Find students who need help, explain why, and propose a specific support action without exposing unnecessary personal data.',
                    owns: ['At-risk identification', 'Support outreach drafts', 'Progress follow-up'],
                    doesNotOwn: ['Disciplinary decisions', 'Grade changes'],
                    triggers: ['A student’s situation needs a human counselor', 'Outreach would contact more than one section'],
                    outputFormat: 'Outcome, student facts, suggested outreach, next step',
                    studentPii: 'minimize',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'at_risk_students',
                    'section_performance',
                    'leaderboard',
                    'send_notification',
                    'award_student_xp',
                ],
            ],
            [
                'slug' => 'communications-lead',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Communications Lead',
                'title' => 'Owns announcements, notifications, and support replies',
                'avatar' => '📣',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Communications Lead',
                    mission: 'Write clear, accurate announcements and replies, and stage every send as an approval card with the exact audience.',
                    owns: ['Announcement drafts', 'Notification audience selection', 'Support ticket replies'],
                    doesNotOwn: ['Policy decisions', 'Sending without approval'],
                    triggers: ['An announcement affects the whole platform', 'A reply needs legal or safeguarding review'],
                    outputFormat: 'Outcome, audience, message draft, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'sections_admin',
                    'announcements_admin',
                    'support_tickets',
                    'post_announcement',
                    'update_announcement',
                    'delete_announcement',
                    'send_notification',
                    'reply_support_ticket',
                ],
            ],
            [
                'slug' => 'operations-controller',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Operations Controller',
                'title' => 'Owns day-to-day workspace operations and readiness',
                'avatar' => '⚙️',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Operations Controller',
                    mission: 'Keep the workspace running: sections, courses, staffing, and operational follow-ups, with every change staged for approval.',
                    owns: ['Section and course operations', 'Operational readiness checks', 'Staff follow-up drafts'],
                    doesNotOwn: ['Platform maintenance', 'Assessment decisions'],
                    triggers: ['An operational issue needs platform maintenance', 'A change affects another workspace'],
                    outputFormat: 'Outcome, operational facts, exact change, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'sections_admin',
                    'courses_admin',
                    'assignments_admin',
                    'exams_admin',
                    'support_tickets',
                    'daily_report',
                    'create_section',
                    'update_section',
                    'delete_section',
                    'create_course',
                    'update_course',
                    'delete_course',
                    'send_notification',
                ],
            ],
            [
                'slug' => 'data-steward',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Data Steward',
                'title' => 'Owns reporting, exports, and data quality',
                'avatar' => '📊',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Data Steward',
                    mission: 'Answer questions with workspace-scoped data, explain the source, and prepare exports only through an approval card.',
                    owns: ['Usage and performance reporting', 'Data quality checks', 'Export preparation'],
                    doesNotOwn: ['Changing source records', 'Cross-workspace reporting'],
                    triggers: ['A report needs platform-wide data', 'Two sources disagree and cannot be reconciled'],
                    outputFormat: 'Outcome, data source, figures, next step',
                    studentPii: 'no_pii',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'at_risk_students',
                    'section_performance',
                    'leaderboard',
                    'ai_action_log',
                    'grades_admin',
                    'submissions_to_grade',
                    'daily_report',
                    'export_report',
                ],
            ],
            [
                'slug' => 'engagement-manager',
                'kind' => AiBot::KIND_SPECIALIST,
                'name' => 'Engagement Manager',
                'title' => 'Owns seasons, badges, XP, and student motivation',
                'avatar' => '🏆',
                'memory_scope' => AiBot::MEMORY_BOT,
                'max_steps' => 8,
                'policy' => self::policy(
                    role: 'Engagement Manager',
                    mission: 'Design motivating seasons, badges, and XP moments, and stage every reward change for approval.',
                    owns: ['Season and badge configuration', 'XP and activity rewards', 'Engagement campaigns'],
                    doesNotOwn: ['Academic grades', 'Enrollment records'],
                    triggers: ['A reward change would affect a live season', 'A campaign targets the whole platform'],
                    outputFormat: 'Outcome, reward mechanics, audience, next step',
                ),
                'allowed_tools' => [
                    'workspace_overview',
                    'students',
                    'user_activity',
                    'leaderboard',
                    'activity_tasks_admin',
                    'season_admin',
                    'manage_badges_rewards',
                    'award_student_xp',
                    'record_activity_task_score',
                    'send_notification',
                ],
                'routines' => [
                    [
                        'name' => 'Monday engagement pulse',
                        'cron' => '0 8 * * 1',
                        'prompt' => 'Summarize last week’s engagement: leaderboard movement, XP awarded, and one concrete campaign idea. Do not change anything.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function specialists(): array
    {
        return array_values(array_filter(
            self::templates(),
            fn (array $template): bool => $template['kind'] === AiBot::KIND_SPECIALIST,
        ));
    }

    /**
     * Create the default roster for a workspace without overwriting an
     * operator’s later edits. Returns the bots keyed by slug.
     *
     * @return array<string, AiBot>
     */
    public static function seedForWorkspace(Workspace $workspace, ?User $createdBy = null): array
    {
        return DB::transaction(function () use ($workspace, $createdBy): array {
            $bots = [];

            foreach (self::templates() as $template) {
                $bot = AiBot::query()
                    ->withoutGlobalScope('workspace')
                    ->where('workspace_id', $workspace->id)
                    ->where('slug', $template['slug'])
                    ->first();

                if (! $bot) {
                    $bot = AiBot::query()->create([
                        'workspace_id' => $workspace->id,
                        'kind' => $template['kind'],
                        'slug' => $template['slug'],
                        'name' => $template['name'],
                        'title' => $template['title'],
                        'avatar' => $template['avatar'],
                        'instructions' => null,
                        'policy' => $template['policy'],
                        'allowed_tools' => $template['allowed_tools'],
                        'model' => null,
                        'max_steps' => $template['max_steps'],
                        'memory_scope' => $template['memory_scope'],
                        'is_active' => true,
                        'created_by' => $createdBy?->id,
                    ]);
                }

                foreach ($template['routines'] ?? [] as $routine) {
                    AiBotRoutine::query()->withoutGlobalScope('workspace')->firstOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'bot_id' => $bot->id,
                            'name' => $routine['name'],
                        ],
                        [
                            'cron' => $routine['cron'],
                            'prompt' => $routine['prompt'],
                            'is_active' => true,
                        ],
                    );
                }

                $bots[$bot->slug] = $bot;
            }

            return $bots;
        });
    }

    /**
     * @param  list<string>  $owns
     * @param  list<string>  $doesNotOwn
     * @param  list<string>  $triggers
     * @param  array<string, mixed>|null  $delegation
     * @return array<string, mixed>
     */
    private static function policy(
        string $role,
        string $mission,
        array $owns,
        array $doesNotOwn,
        string $tone = 'concise, professional, plain language',
        array $triggers = [],
        string $outputFormat = 'Outcome, facts, next step',
        string $modelTier = 'default',
        string $studentPii = 'minimize',
        ?array $delegation = null,
    ): array {
        $policy = [
            'identity' => [
                'role' => $role,
                'mission' => $mission,
                'tone' => $tone,
                'model_tier' => $modelTier,
            ],
            'scope' => [
                'owns' => $owns,
                'does_not_own' => $doesNotOwn,
            ],
            'data_access' => [
                'student_pii' => $studentPii,
            ],
            'escalation' => [
                'triggers' => $triggers,
            ],
            'output' => [
                'format' => $outputFormat,
                'must_include' => ['The outcome', 'The facts the tools returned', 'One next step'],
            ],
        ];

        if ($delegation !== null) {
            $policy['delegation'] = $delegation;
        }

        return $policy;
    }
}
