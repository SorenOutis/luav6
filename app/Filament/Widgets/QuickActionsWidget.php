<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'xl' => 8,
    ];

    // Pair with PendingTasksWidget (4 cols) on xl screens

    protected string $view = 'filament.widgets.quick-actions';

    protected static bool $isLazy = true;

    protected function getViewData(): array
    {
        $service = app(AdminDashboardService::class);
        $data = $service->getQuickActionsData();

        return [
            'totalStudents' => $data['totalStudents'] ?? 0,
            'totalExams' => $data['totalExams'] ?? 0,
            'totalAssignments' => $data['totalAssignments'] ?? 0,
            'pendingSubmissions' => $data['pendingSubmissions'] ?? 0,
            'recentlyBanned' => $data['recentlyBanned'] ?? 0,
            'pendingTickets' => $data['pendingTickets'] ?? 0,
            'pendingAiDrafts' => $data['pendingAiDrafts'] ?? 0,
            'failedJobs' => $data['failedJobs'] ?? 0,
        ];
    }
}
