<?php

namespace App\Filament\Pages;

use App\Support\GraphifyService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class PlatformGraph extends Page
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Platform Architecture Graph';

    protected static ?string $navigationLabel = 'Platform Graph';

    protected static ?string $slug = 'platform-graph';

    protected string $view = 'filament.pages.platform-graph';

    public string $activeTab = 'network';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['network', 'full', 'tree', 'report'], true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsProperty(): array
    {
        return GraphifyService::getStats();
    }

    public function getHasGraphProperty(): bool
    {
        return GraphifyService::hasGraph();
    }

    public function getReportMarkdownProperty(): ?string
    {
        return GraphifyService::getReportMarkdown();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFullscreen')
                ->label('Open in New Tab')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => route('admin.graphify.view', ['type' => $this->activeTab === 'tree' ? 'tree' : 'network']))
                ->openUrlInNewTab(),
            Action::make('regenerate')
                ->label('Regenerate Graph')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Architecture Graph')
                ->modalDescription('This runs Graphify deterministic AST extraction across all code files and recalculates community clusters. Takes around 15–25 seconds.')
                ->action(function (): void {
                    $result = GraphifyService::generate();

                    if ($result['success']) {
                        Notification::make()
                            ->title('Graph regenerated successfully')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Graph regeneration failed')
                            ->body(Str::limit($result['output'], 200))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
