<?php

namespace App\Filament\Pages;

use App\Services\AdminDashboardService;
use App\Support\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

class AdminDashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Command Center';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getSubheading(): ?string
    {
        $context = app(WorkspaceContext::class);
        $workspaceName = $context->workspace()?->name ?? 'Platform';

        return $context->isInspecting()
            ? "Inspecting {$workspaceName} workspace — metrics are scoped to this tenant"
            : "Welcome back — {$workspaceName} overview, 18 widgets, cached for performance. Press ⌘K to search.";
    }

    protected function getHeaderActions(): array
    {
        $context = app(WorkspaceContext::class);

        return [
            Action::make('exitWorkspaceInspection')
                ->label(fn (): string => 'Exit '.($context->workspace()?->name ?? 'workspace').' inspection')
                ->icon('heroicon-o-arrow-left-start-on-rectangle')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && $context->isInspecting())
                ->action(function () use ($context) {
                    $context->stopInspecting();

                    return redirect('/admin');
                }),

            Action::make('refreshDashboard')
                ->label('Refresh data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Clear cache and refresh all widgets (60s cache)')
                ->action(function () {
                    app(AdminDashboardService::class)->clearCache();

                    Notification::make()
                        ->title('Dashboard cache cleared')
                        ->body('Data will refresh on next poll or reload. All 18 widgets updated.')
                        ->success()
                        ->send();
                }),

            Action::make('viewDocs')
                ->label('Dashboard guide')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->tooltip('How to use this dashboard')
                ->action(function () {
                    Notification::make()
                        ->title('Dashboard Guide')
                        ->body('Hero: Command Center with actionable queue • KPIs: Operational Pulse • Row 2: Pending Tasks (4) + Quick Actions (8) • Row 3: Engagement Health • Row 4: AI Usage (4) + System Health (8) + Gamification (4) • Row 5: Content Performance (6) + Trends (8) + Exam Performance (4) • Row 6: Season (12) • Row 7: Activity (6) + Submissions (6) • Row 8: Students (6) + Sections (6) • Row 9: Grading Queue (6) + Support Tickets (6) • Row 10: Retention (6) + Risk (6). All widgets lazy-load and cache for performance.')
                        ->info()
                        ->persistent()
                        ->send();
                }),

            Action::make('exportDashboard')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Export dashboard snapshot (coming soon)')
                ->action(function () {
                    Notification::make()
                        ->title('Export coming soon')
                        ->body('Dashboard export to PDF/CSV will be available in next release. For now, use browser print or individual table exports.')
                        ->info()
                        ->send();
                }),
        ];
    }

    /**
     * Use a 12-column grid for precise widget placement.
     * Widgets declare their own columnSpan (e.g. 12, 6, 4, 8).
     */
    public function getColumns(): int|array
    {
        return 12;
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()))
            ->extraAttributes(['class' => 'admin-dashboard-grid'])
            ->columns(12);
    }
}
