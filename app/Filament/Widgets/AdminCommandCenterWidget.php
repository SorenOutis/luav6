<?php

namespace App\Filament\Widgets;

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

        $students = User::query()->where('is_admin', false);
        $assignmentTargets = DB::table('assignment_user');
        $submittedAssignments = (clone $assignmentTargets)->where('submitted', true)->count();
        $totalAssignmentTargets = $assignmentTargets->count();

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
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'upcomingAssignments' => Assignment::query()
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now()->startOfDay())
                ->orderBy('due_date')
                ->limit(3)
                ->get(),
        ];
    }
}
