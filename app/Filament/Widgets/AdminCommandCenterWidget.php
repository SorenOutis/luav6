<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class AdminCommandCenterWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-command-center';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $activeSeason = Season::query()
            ->where('is_active', true)
            ->first();

        $students = User::query()->where('is_admin', false)->forWorkspace();
        // Drafts are not student work, so their roster rows never count as
        // pending assignments.
        $assignmentTargets = DB::table('assignment_user')
            ->whereIn('assignment_id', Assignment::query()->visibleToStudents()->select('id'));
        $submittedAssignments = (clone $assignmentTargets)->where('submitted', true)->count();
        $totalAssignmentTargets = $assignmentTargets->count();

        // Total XP across all students
        $totalXpEarned = (clone $students)->sum('exp');

        // Games played this week (tower defense runs)
        $gamesPlayedWeek = DB::table('td_runs')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Average score across all exam submissions this week
        $avgScore = ExamSubmission::query()
            ->whereHas('exam')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('score')
            ->avg('score');

        // New students this week
        $newStudentsThisWeek = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Streak leaders (students with 7+ day streaks)
        $streakLeaders = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->where('current_streak', '>=', 7)
            ->orderByDesc('current_streak')
            ->limit(3)
            ->get()
            ->map(fn ($u) => [
                'name' => $u->name,
                'streak' => $u->current_streak,
            ]);

        return [
            'adminName' => auth()->user()?->name ?? 'Admin',
            'activeSeason' => $activeSeason,
            'totalStudents' => (clone $students)->count(),
            'activeToday' => (clone $students)
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now()->startOfDay())
                ->count(),
            'sectionsCount' => Section::query()->count(),
            'liveExamsCount' => Exam::query()->where('status', 'published')->count(),
            'pendingAssignments' => max($totalAssignmentTargets - $submittedAssignments, 0),
            'submissionsToday' => ExamSubmission::query()
                ->whereHas('exam')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'upcomingAssignments' => Assignment::query()
                ->where('status', AssignmentStatus::Published)
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now()->startOfDay())
                ->orderBy('due_date')
                ->limit(3)
                ->get(),
            'totalXpEarned' => $totalXpEarned,
            'gamesPlayedWeek' => $gamesPlayedWeek,
            'avgScore' => $avgScore ? round((float) $avgScore, 1) : 0,
            'newStudentsThisWeek' => $newStudentsThisWeek,
            'streakLeaders' => $streakLeaders,
        ];
    }
}
