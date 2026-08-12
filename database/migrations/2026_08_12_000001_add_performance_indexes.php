<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance — index the columns every page load filters, joins and sorts on.
 *
 * `$table->foreignId(...)->constrained()` creates a foreign KEY, not an index.
 * MySQL happens to add one implicitly; SQLite and Postgres do NOT. This app
 * runs SQLite in dev/Docker and Postgres in production, so the hottest tables
 * (`gamification_histories`, `section_user`, `section_progress`, `course_user`,
 * `assignment_user`, `exam_submissions`) were being full-scanned on every
 * dashboard, leaderboard and navigation request.
 *
 * Every index below maps to a query that runs on a page render:
 *
 *  - gamification_histories(user_id, created_at)
 *      DashboardController heatmap  → where user_id = ? and created_at >= ?
 *      LeaderboardService weekStats → whereIn user_id and created_at >= ?
 *  - gamification_histories(user_id, season_id)
 *      DashboardController xp/points breakdown
 *  - section_user(user_id, season_id) / (section_id, season_id)
 *      every $user->sections()->wherePivot('season_id', ...) call
 *  - section_progress(section_id, exp)
 *      the ROW_NUMBER() OVER (PARTITION BY section_id ORDER BY exp DESC) rank
 *  - exam_submissions(user_id, exam_id) / (exam_id) / (exam_part_id)
 *      UpcomingExamsService withCount + admin submission screens
 *  - exams(status, exam_date) / (section_id)
 *      UpcomingExamsService: where status != draft order by exam_date
 *  - notifications(notifiable_type, notifiable_id, read_at)
 *      HandleInertiaRequests unread badge — runs on EVERY navigation.
 *      `morphs()` already indexes the first two columns; appending read_at
 *      makes the `whereNull('read_at')` count a covering index lookup instead
 *      of an index scan plus a row fetch per notification.
 *
 * Guarded with hasTable/hasColumn/hasIndex so it is safe to re-run and safe on
 * MySQL where some of these already exist implicitly.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private array $indexes = [
        'gamification_histories' => [
            'gam_hist_user_created_idx' => ['user_id', 'created_at'],
            'gam_hist_user_season_idx' => ['user_id', 'season_id'],
            'gam_hist_section_idx' => ['section_id'],
        ],
        'section_user' => [
            'section_user_user_season_idx' => ['user_id', 'season_id'],
            'section_user_section_season_idx' => ['section_id', 'season_id'],
        ],
        'section_progress' => [
            // Serves the windowed rank query and the per-section ordering.
            'section_progress_section_exp_idx' => ['section_id', 'exp'],
        ],
        'course_user' => [
            'course_user_user_idx' => ['user_id'],
            'course_user_course_idx' => ['course_id'],
        ],
        'assignment_user' => [
            'assignment_user_user_idx' => ['user_id'],
            'assignment_user_assignment_idx' => ['assignment_id'],
        ],
        'exam_submissions' => [
            'exam_subs_user_exam_idx' => ['user_id', 'exam_id'],
            'exam_subs_exam_idx' => ['exam_id'],
            'exam_subs_part_idx' => ['exam_part_id'],
        ],
        'exam_parts' => [
            'exam_parts_exam_sort_idx' => ['exam_id', 'sort_order'],
        ],
        'exams' => [
            'exams_status_date_idx' => ['status', 'exam_date'],
            'exams_section_idx' => ['section_id'],
        ],
        'notifications' => [
            'notifications_notifiable_read_idx' => ['notifiable_type', 'notifiable_id', 'read_at'],
        ],
        'announcements' => [
            'announcements_active_idx' => ['is_active'],
        ],
        'season_progress' => [
            'season_progress_season_idx' => ['season_id'],
        ],
        'badge_user' => [
            'badge_user_user_idx' => ['user_id'],
        ],
        'seasons' => [
            'seasons_active_idx' => ['is_active'],
        ],
        'sections' => [
            'sections_season_idx' => ['season_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $name => $columns) {
                if (! $this->columnsExist($table, $columns)) {
                    continue;
                }

                if ($this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($definitions) as $name) {
                if (! $this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Index names are compared case-insensitively: Postgres folds unquoted
     * identifiers to lower case, so a name can come back in a different case
     * than it was created with.
     */
    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => strcasecmp((string) ($index['name'] ?? ''), $name) === 0);
    }
};
