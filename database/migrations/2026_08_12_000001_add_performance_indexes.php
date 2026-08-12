<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance — index the columns every page load filters, joins and sorts on.
 *
 * `$table->foreignId(...)->constrained()` creates a foreign KEY, not an index.
 * MySQL/InnoDB is the one engine that adds one implicitly; PostgreSQL does not:
 *
 *   "Because this is not always needed, and there are many choices available on
 *    how to index, the declaration of a foreign key constraint does not
 *    automatically create an index on the referencing columns."
 *   — https://www.postgresql.org/docs/current/ddl-constraints.html
 *
 * Production runs PostgreSQL (see .env.production.example), so the hottest
 * tables were being sequentially scanned on every dashboard, leaderboard and
 * navigation request. SQLite (dev/Docker) has the same gap.
 *
 * Every index below maps to a query that runs on a page render:
 *
 *  - gamification_histories(user_id, created_at)
 *      DashboardController heatmap  → where user_id = ? and created_at >= ?
 *      LeaderboardService weekStats → whereIn user_id and created_at >= ?
 *      This table grows fastest of any (one row per XP event), so it degrades
 *      the most over time.
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
 *      makes the `whereNull('read_at')` count a covering lookup.
 *
 * ⚠️ PostgreSQL locking: a plain CREATE INDEX takes a SHARE lock that blocks
 * INSERT/UPDATE/DELETE on the table until the build finishes. start.sh runs
 * `php artisan migrate --force` on every deploy, so on a large table that is a
 * write stall during rollout. This migration therefore uses
 * CREATE INDEX CONCURRENTLY on PostgreSQL, which does not block writes.
 *
 * CONCURRENTLY cannot run inside a transaction block, so $withinTransaction is
 * false. That means the migration is NOT atomic: if it fails partway, the
 * indexes it already created stay. It is written to be safely re-runnable
 * (IF NOT EXISTS / hasIndex guards), so re-running `migrate` finishes the job.
 *
 * ⚠️ A cancelled CONCURRENTLY build can leave an INVALID index behind, which
 * Postgres will not use. To find and clean those up:
 *
 *   SELECT indexrelid::regclass FROM pg_index WHERE NOT indisvalid;
 *   DROP INDEX CONCURRENTLY <name>;   -- then re-run this migration
 */
return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY is rejected inside a transaction block, and
     * Laravel wraps migrations in one on PostgreSQL by default.
     */
    public $withinTransaction = false;

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

                $this->createIndex($table, $name, $columns);
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

                $this->dropIndex($table, $name);
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createIndex(string $table, string $name, array $columns): void
    {
        if ($this->isPostgres()) {
            // Non-blocking build so a deploy never stalls writes.
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)',
                $this->quote($name),
                $this->quote($table),
                collect($columns)->map(fn ($c) => $this->quote($c))->join(', ')
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if ($this->isPostgres()) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$this->quote($name));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
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

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
