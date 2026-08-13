<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminDashboard;
use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\AdminActivityTrendChart;
use App\Filament\Widgets\AdminAnalyticsOverview;
use App\Filament\Widgets\AdminCommandCenterWidget;
use App\Filament\Widgets\ExamPerformanceWidget;
use App\Filament\Widgets\LatestExamSubmissionsWidget;
use App\Filament\Widgets\NeuronUsageWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\SeasonProgressWidget;
use App\Filament\Widgets\SectionComparisonWidget;
use App\Filament\Widgets\StudentRiskWidget;
use App\Filament\Widgets\TopStudentsWidget;
use App\Http\Middleware\SanitizeInput;
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
            ->plugins([
                FilamentJobsMonitorPlugin::make(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminCommandCenterWidget::class,
                AdminAnalyticsOverview::class,
                NeuronUsageWidget::class,
                SeasonProgressWidget::class,
                AdminActivityTrendChart::class,
                ExamPerformanceWidget::class,
                ActivityFeedWidget::class,
                LatestExamSubmissionsWidget::class,
                TopStudentsWidget::class,
                SectionComparisonWidget::class,
                StudentRiskWidget::class,
                QuickActionsWidget::class,
            ])
            ->middleware([
                SanitizeInput::class,
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
