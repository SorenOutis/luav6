<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminDashboard;
use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\AdminActivityTrendChart;
use App\Filament\Widgets\AdminAnalyticsOverview;
use App\Filament\Widgets\AdminCommandCenterWidget;
use App\Filament\Widgets\AssignmentGradingQueueWidget;
use App\Filament\Widgets\ContentPerformanceWidget;
use App\Filament\Widgets\EngagementMetricsWidget;
use App\Filament\Widgets\ExamPerformanceWidget;
use App\Filament\Widgets\GamificationInsightsWidget;
use App\Filament\Widgets\LatestExamSubmissionsWidget;
use App\Filament\Widgets\NeuronUsageWidget;
use App\Filament\Widgets\PendingTasksWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentSupportTicketsWidget;
use App\Filament\Widgets\RetentionInsightsWidget;
use App\Filament\Widgets\SeasonProgressWidget;
use App\Filament\Widgets\SectionComparisonWidget;
use App\Filament\Widgets\StudentRiskWidget;
use App\Filament\Widgets\SystemHealthWidget;
use App\Filament\Widgets\TopStudentsWidget;
use App\Http\Middleware\SecurityHeaders;
use App\Support\FaviconUrl;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('LuaV6 Admin')
            ->favicon(fn (): string => FaviconUrl::url())
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Zinc,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->navigationGroups([
                'Learning',
                'Community',
                'Gamification',
                'Administration',
                'Settings',
                'Games',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->plugins([
                FilamentJobsMonitorPlugin::make(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Hero / KPI - full width
                AdminCommandCenterWidget::class,
                AdminAnalyticsOverview::class,

                // Pending tasks + Quick actions (4 + 8 on xl) -> actionable first row
                PendingTasksWidget::class,
                QuickActionsWidget::class,

                // Engagement health full width
                EngagementMetricsWidget::class,

                // AI usage + System health + Gamification
                NeuronUsageWidget::class,
                SystemHealthWidget::class,
                GamificationInsightsWidget::class,

                // Content performance + Trends + Exam performance
                ContentPerformanceWidget::class,
                AdminActivityTrendChart::class,
                ExamPerformanceWidget::class,

                // Season progress full width
                SeasonProgressWidget::class,

                // Activity + submissions (6 + 6)
                ActivityFeedWidget::class,
                LatestExamSubmissionsWidget::class,

                // Students + sections (6 + 6)
                TopStudentsWidget::class,
                SectionComparisonWidget::class,

                // Grading + Tickets (6 + 6)
                AssignmentGradingQueueWidget::class,
                RecentSupportTicketsWidget::class,

                // Retention + Risk (6 + 6)
                RetentionInsightsWidget::class,
                StudentRiskWidget::class,
            ])
            ->middleware([
                SecurityHeaders::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
