<?php

namespace App\Filament\Pages;

use App\Services\AiBudgetReportingService;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class AiUsageDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'AI Usage & Budgets';

    protected static ?string $navigationLabel = 'AI Usage & Budgets';

    protected string $view = 'filament.pages.ai-usage-dashboard';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(AiBudgetReportingService::class)->dashboard();
    }
}
