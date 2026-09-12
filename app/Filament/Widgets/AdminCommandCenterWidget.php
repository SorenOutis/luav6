<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\Widget;

class AdminCommandCenterWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 12;

    protected string $view = 'filament.widgets.admin-command-center';

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(AdminDashboardService::class);
        $data = $service->getCommandCenterData();

        return [
            'adminName' => auth()->user()?->name ?? 'Admin',
            'activeSeason' => $data['activeSeason'] ?? null,
            'totalStudents' => $data['totalStudents'] ?? 0,
            'activeToday' => $data['activeToday'] ?? 0,
            'sectionsCount' => $data['sectionsCount'] ?? 0,
            'liveExamsCount' => $data['liveExams'] ?? 0,
            'pendingAssignments' => max(($data['totalAssignmentTargets'] ?? 0) - ($data['submittedAssignmentTargets'] ?? 0), 0),
            'submissionsToday' => $data['submissionsToday'] ?? 0,
            'upcomingAssignments' => $data['upcomingAssignments'] ?? collect(),
            'totalXpEarned' => $data['totalXp'] ?? 0,
            'gamesPlayedWeek' => $data['gamesWeek'] ?? 0,
            'avgScore' => $data['avgScore7d'] ?? 0,
            'newStudentsThisWeek' => $data['newThisWeek'] ?? 0,
            'streakLeaders' => $data['streakLeaders'] ?? collect(),
            'pendingTickets' => $data['pendingTickets'] ?? 0,
            'pendingAiDrafts' => $data['pendingAiDrafts'] ?? 0,
            'failedJobs' => $data['failedJobs'] ?? 0,
            'pendingSubmissions' => $data['pendingSubmissions'] ?? 0,
        ];
    }
}
