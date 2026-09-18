<?php

namespace App\Filament\Pages;

use App\Models\Season;
use App\Models\Section;
use App\Models\Workspace;
use App\Services\LeaderboardService;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Admin leaderboards page.
 *
 * Renders per-section season leaderboards as an accordion: every section
 * shows its top five students, and expanding a section loads the full ranked
 * table. Tenant admins see their own workspace's sections; super admins get
 * an extra workspace filter to drill into any workspace (or see everything
 * platform-wide while not inspecting).
 *
 * Each section header carries a toggle that controls whether the section's
 * leaderboard is shown to students on their dashboard and leaderboard page.
 */
class Leaderboards extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Leaderboards';

    protected static ?string $navigationLabel = 'Leaderboards';

    protected string $view = 'filament.pages.leaderboards';

    public ?int $seasonId = null;

    public ?int $workspaceId = null;

    /** @var array<int, bool> */
    public array $expandedSections = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        $this->seasonId = $this->defaultSeasonId();
    }

    public function toggleSection(int $sectionId): void
    {
        if (isset($this->expandedSections[$sectionId])) {
            unset($this->expandedSections[$sectionId]);

            return;
        }

        $this->expandedSections[$sectionId] = true;
    }

    /**
     * Flip whether this section's leaderboard is shown to students.
     *
     * The Section workspace global scope keeps tenant admins inside their own
     * workspace, so a foreign section id simply resolves to nothing.
     */
    public function toggleLeaderboardVisibility(int $sectionId): void
    {
        $section = Section::query()->find($sectionId);

        if (! $section) {
            return;
        }

        $section->leaderboard_enabled = ! $section->leaderboard_enabled;
        $section->save();

        $notification = Notification::make()
            ->title($section->leaderboard_enabled
                ? 'Leaderboard visible to students'
                : 'Leaderboard hidden from students')
            ->body($section->leaderboard_enabled
                ? "Students in {$section->name} can now see their leaderboard again."
                : "Students in {$section->name} will no longer see their leaderboard.");

        if ($section->leaderboard_enabled) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    public function updatedSeasonId(): void
    {
        $this->expandedSections = [];

        // The workspace filter is driven by the selected season's enrollments,
        // so a workspace that lost its enrollments must be dropped.
        if ($this->workspaceId !== null && $this->workspaceCandidates()->doesntContain('id', $this->workspaceId)) {
            $this->workspaceId = null;
        }

        if ($this->seasonCandidates()->doesntContain('id', $this->seasonId)) {
            $this->seasonId = $this->defaultSeasonId();
        }
    }

    public function updatedWorkspaceId(): void
    {
        // "All workspaces" posts the integer 0; normalize it back to null.
        $this->workspaceId = $this->workspaceId ?: null;

        $this->expandedSections = [];

        if ($this->seasonId !== null && $this->seasonCandidates()->doesntContain('id', $this->seasonId)) {
            $this->seasonId = $this->defaultSeasonId();
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $season = $this->selectedSeason();
        $seasonCandidates = $this->seasonCandidates();
        $boards = $this->buildLeaderboards($season);

        return [
            'seasonOptions' => $seasonCandidates
                ->mapWithKeys(fn (Season $season): array => [$season->id => $season->name])
                ->all(),
            'workspaceOptions' => $this->workspaceCandidates()
                ->mapWithKeys(fn (Workspace $workspace): array => [$workspace->id => $workspace->name])
                ->all(),
            'showWorkspaceFilter' => $this->showWorkspaceFilter(),
            'leaderboards' => $boards['sections'],
            'totalSections' => $boards['total'],
            'activeSeasonName' => $season?->name,
        ];
    }

    /**
     * @return Collection<int, Season>
     */
    protected function seasonCandidates(): Collection
    {
        return app(LeaderboardService::class)->availableSeasons(auth()->user(), $this->workspaceId);
    }

    /**
     * @return Collection<int, Workspace>
     */
    protected function workspaceCandidates(): Collection
    {
        if (! $this->showWorkspaceFilter()) {
            return collect();
        }

        return app(LeaderboardService::class)->workspacesWithEnrollments($this->selectedSeason());
    }

    protected function showWorkspaceFilter(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isSuperAdmin() && ! app(WorkspaceContext::class)->isInspecting());
    }

    protected function selectedSeason(): ?Season
    {
        return $this->seasonId ? Season::query()->find($this->seasonId) : null;
    }

    /**
     * Build the per-section boards, replacing expanded sections with their
     * full leaderboards, and group them by workspace.
     *
     * @return array{sections: array<int, array<string, mixed>>, total: int}
     */
    protected function buildLeaderboards(?Season $season): array
    {
        $user = auth()->user();
        $service = app(LeaderboardService::class);

        $boards = $service->forAdminSections($user, $season, $this->workspaceId, maxVisibleUsers: 5);

        foreach (array_keys($this->expandedSections) as $sectionId) {
            $index = collect($boards)->search(
                fn (array $board): bool => (int) $board['sectionId'] === (int) $sectionId,
            );

            if ($index !== false) {
                $boards[$index] = $service->forAdminSection($user, $season, (int) $sectionId) ?: $boards[$index];
            }
        }

        $groups = [];

        foreach ($boards as $board) {
            $key = (string) ($board['workspaceId'] ?? 'none');

            $groups[$key] ??= [
                'workspaceId' => $board['workspaceId'] ?? null,
                'workspaceName' => $board['workspaceName'] ?? 'No workspace',
                'sections' => [],
            ];

            $groups[$key]['sections'][] = $board;
        }

        return [
            'sections' => array_values($groups),
            'total' => $service->countAdminSections($user, $season, $this->workspaceId),
        ];
    }

    protected function defaultSeasonId(): ?int
    {
        $candidates = $this->seasonCandidates();

        if ($candidates->isEmpty()) {
            return null;
        }

        $current = Season::current();

        if ($current && $candidates->contains('id', $current->id)) {
            return $current->id;
        }

        return $candidates->first()?->id;
    }
}
