<?php

namespace App\Filament\Resources\PlatformAiBots\Pages;

use App\Filament\Pages\AiSettings;
use App\Filament\Resources\PlatformAiBots\PlatformAiBotResource;
use App\Services\AiBotOperationsDashboardService;
use App\Support\AiBotsGate;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Read-only live view of how the bot roster is working.
 *
 * This page never writes. Policy edits stay on the control-center table; a
 * bot's detail is a slide-over so operators can inspect without leaving the
 * live feed.
 */
class BotOperations extends Page
{
    protected static string $resource = PlatformAiBotResource::class;

    protected static ?string $title = 'Bot Operations';

    protected static ?string $breadcrumb = 'Operations';

    protected string $view = 'filament.pages.bot-operations';

    #[Url(as: 'workspace', except: '')]
    public string $workspaceFilter = '';

    #[Url(as: 'period', except: '7d')]
    public string $periodFilter = AiBotOperationsDashboardService::PERIOD_7_DAYS;

    #[Url(as: 'activity', except: 'all')]
    public string $activityFilter = AiBotOperationsDashboardService::ACTIVITY_ALL;

    #[Url(as: 'bot', except: '')]
    public string $botFilter = '';

    /** Pausing only stops the 15-second refresh; data remains readable. */
    public bool $polling = true;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('togglePolling')
                ->label(fn (): string => $this->polling ? 'Pause live updates' : 'Resume live updates')
                ->icon(fn (): string => $this->polling ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color('gray')
                ->action(fn () => $this->togglePolling()),
            Action::make('manageBots')
                ->label('Manage bots')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(PlatformAiBotResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $selectedBotId = ctype_digit($this->botFilter) ? (int) $this->botFilter : null;

        return array_merge(
            app(AiBotOperationsDashboardService::class)->dashboard(
                $this->workspaceFilter,
                $this->periodFilter,
                $this->activityFilter,
                $selectedBotId,
            ),
            [
                'bots_enabled' => AiBotsGate::enabled(),
                'bots_disabled_message' => AiBotsGate::disabledMessage(),
                'ai_bots_settings_url' => AiSettings::getUrl(),
                'edit_bot_url' => fn (int $botId): string => PlatformAiBotResource::getUrl('edit', [
                    'record' => $botId,
                ]),
            ],
        );
    }

    public function togglePolling(): void
    {
        $this->polling = ! $this->polling;
    }

    public function setPeriod(string $period): void
    {
        $allowed = array_keys(app(AiBotOperationsDashboardService::class)->periodOptions());

        if (in_array($period, $allowed, true)) {
            $this->periodFilter = $period;
        }
    }

    public function setActivity(string $activity): void
    {
        $allowed = array_keys(app(AiBotOperationsDashboardService::class)->activityTypeOptions());

        if (in_array($activity, $allowed, true)) {
            $this->activityFilter = $activity;
        }
    }

    public function openBot(int $botId): void
    {
        $this->botFilter = (string) $botId;
    }

    public function closeBot(): void
    {
        $this->botFilter = '';
    }
}
