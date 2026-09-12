<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\Widget;

class ActivityFeedWidget extends Widget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 6,
    ];

    protected string $view = 'filament.widgets.activity-feed';

    protected ?string $pollingInterval = '60s';

    protected static bool $isLazy = true;

    protected function getViewData(): array
    {
        $service = app(AdminDashboardService::class);

        return [
            'activities' => $service->getActivityFeed(20),
        ];
    }
}
