<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum Designer: watches how course content actually matures.
 *
 * Lesson depth is measured with float-safe SQL (SQLite truncates integer
 * division), counting lessons that have neither content nor a quiz attached.
 */
class CurriculumWatcherCheck implements BotWatcherCheck
{
    public const MIN_QUIZ_COVERAGE = 0.4;

    public function domain(): string
    {
        return 'curriculum';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];

        $courseIds = DB::table('courses')
            ->where('workspace_id', $workspace->id)
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return [];
        }

        $lessonStats = DB::table('lessons')
            ->join('course_modules', 'lessons.course_module_id', '=', 'course_modules.id')
            ->join('courses', 'course_modules.course_id', '=', 'courses.id')
            ->where('courses.workspace_id', $workspace->id)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when coalesce(lessons.content, '') = '' and lessons.video_url is null and coalesce(lessons.media_attachments, '[]') = '[]' then 1 else 0 end) as empty")
            ->first();

        $totalLessons = (int) ($lessonStats?->total ?? 0);
        $emptyLessons = (int) ($lessonStats?->empty ?? 0);

        if ($totalLessons > 3 && $emptyLessons > 0) {
            $share = $emptyLessons / $totalLessons;

            $findings[] = new BotFinding(
                fingerprint: 'empty_lesson_drafts',
                severity: $share > self::MIN_QUIZ_COVERAGE ? 'warning' : 'info',
                title: 'Lessons still placeholders',
                summary: sprintf(
                    '%d of %d lessons have no content, video, or attachments yet (%.0f%% of the catalogue).',
                    $emptyLessons,
                    $totalLessons,
                    $share * 100,
                ),
                evidence: [
                    'Placeholder lessons: '.$emptyLessons,
                    'Total lessons: '.$totalLessons,
                    'Catalogue coverage: '.round((1 - $share) * 100).'%',
                ],
                suggestedAction: 'Prioritize filling the placeholder lessons in the most-enrolled courses; drafting content for approval is a natural next step.',
            );
        }

        return $findings;
    }
}
