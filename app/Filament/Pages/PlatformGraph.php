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

    public string $search = '';

    public string $selectedDomain = 'all';

    public ?int $inspectSubsystemId = null;

    public ?string $focusSubsystemHub = null;

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['network', 'subsystems', 'full', 'tree', 'report'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function filterDomain(string $domain): void
    {
        $this->selectedDomain = $domain;
    }

    public function inspectSubsystem(?int $id): void
    {
        $this->inspectSubsystemId = $id;
    }

    public function closeInspection(): void
    {
        $this->inspectSubsystemId = null;
    }

    public function focusInGraph(string $hub): void
    {
        $this->focusSubsystemHub = $hub;
        $this->activeTab = 'network';
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSubsystemsProperty(): array
    {
        return GraphifyService::getSubsystems(
            $this->selectedDomain === 'all' ? null : $this->selectedDomain,
            $this->search !== '' ? $this->search : null
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDomainSummaryProperty(): array
    {
        return GraphifyService::getDomainSummary();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDomainDefinitionsProperty(): array
    {
        return GraphifyService::getDomainDefinitions();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInspectedSubsystemProperty(): ?array
    {
        if ($this->inspectSubsystemId === null) {
            return null;
        }

        return GraphifyService::getSubsystem($this->inspectSubsystemId);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('organize')
                ->label('Organize Subsystems')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action(function (): void {
                    GraphifyService::organizeCommunities();

                    Notification::make()
                        ->title('Subsystems Organized')
                        ->body('Architecture domains, labels, and graph models refreshed successfully.')
                        ->success()
                        ->send();
                }),
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
                ->modalDescription('This runs Graphify deterministic AST extraction across all code files, organizes architectural subsystems, and recalculates cluster metrics. Takes around 15–25 seconds.')
                ->action(function (): void {
                    $result = GraphifyService::generate();

                    if ($result['success']) {
                        Notification::make()
                            ->title('Graph regenerated and organized successfully')
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
