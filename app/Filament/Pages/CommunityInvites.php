<?php

namespace App\Filament\Pages;

use App\Models\CommunityInvite;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class CommunityInvites extends Page
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->is_admin;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Lounge Invites';

    protected static ?string $navigationLabel = 'Lounge Invites';

    protected static ?string $slug = 'community-invites';

    protected string $view = 'filament.pages.community-invites';

    public string $searchQuery = '';

    public ?int $selectedUserId = null;

    public string $statusFilter = 'all'; // all, accepted, pending

    public string $sectionFilter = 'all'; // all, unassigned, or section ID string

    /** @var array<int, int> */
    public array $selectedStudentIds = [];

    public ?int $workspaceId = null;

    public function mount(): void
    {
        $context = app(WorkspaceContext::class);
        $this->workspaceId = $context->id();
    }

    public function selectSection(int|string|null $section = null): void
    {
        if ($section === null || $section === '' || $section === 'all') {
            $this->sectionFilter = 'all';
        } elseif ($section === 'unassigned') {
            $this->sectionFilter = 'unassigned';
        } else {
            $this->sectionFilter = (string) (int) $section;
        }

        $this->selectedStudentIds = [];
    }

    public function toggleStudentSelection(int $userId): void
    {
        if (in_array($userId, $this->selectedStudentIds, true)) {
            $this->selectedStudentIds = array_values(array_diff($this->selectedStudentIds, [$userId]));

            return;
        }

        $this->selectedStudentIds[] = $userId;
        $this->selectedStudentIds = array_values(array_unique(array_map('intval', $this->selectedStudentIds)));
    }

    public function toggleSelectAllVisible(): void
    {
        $visibleIds = $this->studentsRoster
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($visibleIds === []) {
            $this->selectedStudentIds = [];

            return;
        }

        $allSelected = count(array_intersect($visibleIds, $this->selectedStudentIds)) === count($visibleIds);

        if ($allSelected) {
            $this->selectedStudentIds = array_values(array_diff($this->selectedStudentIds, $visibleIds));

            return;
        }

        $this->selectedStudentIds = array_values(array_unique(array_merge($this->selectedStudentIds, $visibleIds)));
    }

    public function selectSectionStudents(int $sectionId): void
    {
        $this->sectionFilter = (string) $sectionId;

        $this->selectedStudentIds = $this->studentsRoster
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function clearSelectedStudents(): void
    {
        $this->selectedStudentIds = [];
    }

    public function getSelectedStudentProperty(): ?User
    {
        if (! $this->selectedUserId) {
            return null;
        }

        $workspaceId = $this->currentWorkspaceId();

        return User::query()
            ->with([
                'communityInvite',
                'sections' => fn (BelongsToMany $query) => $this->scopeSectionsToWorkspace($query, $workspaceId),
            ])
            ->find($this->selectedUserId);
    }

    /**
     * Get all sections in the current workspace, sorted alphabetically.
     *
     * @return Collection<int, Section>
     */
    public function getWorkspaceSectionsProperty(): Collection
    {
        $workspaceId = $this->currentWorkspaceId();

        return Section::withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $q) => $q->where('sections.workspace_id', $workspaceId))
            ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('sections.workspace_id'))
            ->withCount([
                'users as students_count' => fn (Builder $q) => $q->where('users.is_admin', false),
                'users as unlocked_students_count' => fn (Builder $q) => $q
                    ->where('users.is_admin', false)
                    ->whereHas('communityInvite', fn (Builder $sub) => $sub->whereNotNull('accepted_at')),
            ])
            ->orderBy('sections.name')
            ->get();
    }

    public function getActiveSectionProperty(): ?Section
    {
        if ($this->sectionFilter === 'all' || $this->sectionFilter === 'unassigned' || ! is_numeric($this->sectionFilter)) {
            return null;
        }

        $sectionId = (int) $this->sectionFilter;

        return $this->workspaceSections->firstWhere('id', $sectionId);
    }

    public function getUnassignedStudentsCountProperty(): int
    {
        $workspaceId = $this->currentWorkspaceId();

        return $this->getStudentsBaseQuery()
            ->whereDoesntHave('sections', fn (Builder $q) => $q
                ->withoutGlobalScope('workspace')
                ->when($workspaceId, fn (Builder $sq) => $sq->where('sections.workspace_id', $workspaceId))
                ->when(! $workspaceId, fn (Builder $sq) => $sq->whereNull('sections.workspace_id')))
            ->count();
    }

    public function selectStudent(int $userId): void
    {
        $student = User::query()->find($userId);
        if (! $student) {
            return;
        }

        $workspaceId = $this->resolveTargetWorkspaceId($student);

        // Auto-provision invite if none exists yet
        CommunityInvite::query()->firstOrCreate(
            ['user_id' => $student->id],
            [
                'workspace_id' => $workspaceId,
                'token' => CommunityInvite::generateToken(),
                'invited_by' => (int) Filament::auth()->id(),
            ],
        );

        $this->selectedUserId = $student->id;
    }

    public function closeSelectedStudent(): void
    {
        $this->selectedUserId = null;
    }

    public function regenerateInvite(int $userId): void
    {
        $student = User::query()->find($userId);
        if (! $student) {
            return;
        }

        $workspaceId = $this->resolveTargetWorkspaceId($student);

        $invite = CommunityInvite::query()->firstOrNew(['user_id' => $student->id]);
        $invite->workspace_id = $workspaceId;
        $invite->token = CommunityInvite::generateToken();
        $invite->invited_by = (int) Filament::auth()->id();
        $invite->accepted_at = null; // Re-require accepting with fresh secure link
        $invite->save();

        $this->selectedUserId = $student->id;

        Notification::make()
            ->title('New Invite Link Generated')
            ->body("A fresh invite link was created for {$student->name}. Any previous link is now permanently invalidated.")
            ->success()
            ->send();
    }

    public function grantAccess(int $userId): void
    {
        $student = User::query()->find($userId);
        if (! $student) {
            return;
        }

        $this->provisionAndAcceptInvite($student);

        $this->selectedUserId = $student->id;

        Notification::make()
            ->title('Lounge Access Granted')
            ->body("{$student->name} can now enter the Community Lounge immediately.")
            ->success()
            ->send();
    }

    public function revokeAccess(int $userId): void
    {
        $student = User::query()->find($userId);
        if (! $student) {
            return;
        }

        $invite = CommunityInvite::query()->where('user_id', $student->id)->first();
        if ($invite) {
            $invite->update(['accepted_at' => null]);
        }

        $this->selectedUserId = $student->id;

        Notification::make()
            ->title('Lounge Access Revoked')
            ->body("{$student->name}'s community lounge access has been locked.")
            ->warning()
            ->send();
    }

    public function grantSelectedAccess(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedStudentIds)));
        if ($ids === []) {
            return;
        }

        $students = $this->getStudentsBaseQuery()
            ->whereIn('users.id', $ids)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        foreach ($students as $student) {
            $this->provisionAndAcceptInvite($student);
        }

        $count = $students->count();
        $this->selectedStudentIds = [];

        Notification::make()
            ->title('Lounge Access Granted')
            ->body("Unlocked Community Lounge access for {$count} ".str('student')->plural($count).'.')
            ->success()
            ->send();
    }

    public function revokeSelectedAccess(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedStudentIds)));
        if ($ids === []) {
            return;
        }

        $students = $this->getStudentsBaseQuery()
            ->whereIn('users.id', $ids)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        CommunityInvite::query()
            ->whereIn('user_id', $students->pluck('id'))
            ->update(['accepted_at' => null]);

        $count = $students->count();
        $this->selectedStudentIds = [];

        Notification::make()
            ->title('Lounge Access Revoked')
            ->body("Locked Community Lounge access for {$count} ".str('student')->plural($count).'.')
            ->warning()
            ->send();
    }

    public function grantSectionAccess(int $sectionId): void
    {
        $section = $this->workspaceSections->firstWhere('id', $sectionId);
        if (! $section) {
            return;
        }

        $students = $this->getStudentsBaseQuery()
            ->whereHas('sections', fn (Builder $q) => $q
                ->withoutGlobalScope('workspace')
                ->where('sections.id', $section->id))
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        foreach ($students as $student) {
            $this->provisionAndAcceptInvite($student);
        }

        Notification::make()
            ->title('Section Access Granted')
            ->body("Unlocked Community Lounge access for all {$students->count()} ".str('student')->plural($students->count())." in {$section->name}.")
            ->success()
            ->send();
    }

    public function revokeSectionAccess(int $sectionId): void
    {
        $section = $this->workspaceSections->firstWhere('id', $sectionId);
        if (! $section) {
            return;
        }

        $students = $this->getStudentsBaseQuery()
            ->whereHas('sections', fn (Builder $q) => $q
                ->withoutGlobalScope('workspace')
                ->where('sections.id', $section->id))
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        CommunityInvite::query()
            ->whereIn('user_id', $students->pluck('id'))
            ->update(['accepted_at' => null]);

        Notification::make()
            ->title('Section Access Revoked')
            ->body("Locked Community Lounge access for {$section->name}.")
            ->warning()
            ->send();
    }

    /**
     * Get search suggestions when typing in the search box.
     *
     * @return Collection<int, User>
     */
    public function getSearchResultsProperty(): Collection
    {
        $query = trim($this->searchQuery);
        if ($query === '') {
            return collect();
        }

        $baseQuery = $this->getStudentsBaseQuery();
        $this->applySectionFilter($baseQuery);

        return $baseQuery
            ->where(function (Builder $q) use ($query) {
                $q->where('users.name', 'like', "%{$query}%")
                    ->orWhere('users.email', 'like', "%{$query}%");
            })
            ->orderBy('users.name')
            ->limit(10)
            ->get();
    }

    /**
     * Get students roster list for the workspace table, sorted by section then student name.
     *
     * @return Collection<int, User>
     */
    public function getStudentsRosterProperty(): Collection
    {
        $workspaceId = $this->currentWorkspaceId();
        $query = $this->getStudentsBaseQuery();

        $this->applySectionFilter($query);

        if ($this->searchQuery !== '') {
            $search = trim($this->searchQuery);
            $query->where(function (Builder $q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        if ($this->statusFilter === 'accepted') {
            $query->whereHas('communityInvite', fn (Builder $q) => $q->whereNotNull('accepted_at'));
        } elseif ($this->statusFilter === 'pending') {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('communityInvite')
                    ->orWhereHas('communityInvite', fn (Builder $sub) => $sub->whereNull('accepted_at'));
            });
        }

        $sectionSortSubquery = Section::withoutGlobalScope('workspace')
            ->selectRaw('MIN(sections.name)')
            ->join('section_user', 'section_user.section_id', '=', 'sections.id')
            ->whereColumn('section_user.user_id', 'users.id')
            ->when($workspaceId, fn (Builder $q) => $q->where('sections.workspace_id', $workspaceId))
            ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('sections.workspace_id'));

        return $query
            ->orderByRaw('CASE WHEN ('.$sectionSortSubquery->toSql().') IS NULL THEN 1 ELSE 0 END ASC', $sectionSortSubquery->getBindings())
            ->orderBy($sectionSortSubquery)
            ->orderBy('users.name')
            ->limit(200)
            ->get();
    }

    public function getInviteUrl(User $student): string
    {
        $token = $student->communityInvite?->token;
        if (! $token) {
            return '';
        }

        return url("/community/join/{$token}");
    }

    protected function currentWorkspaceId(): ?int
    {
        return $this->workspaceId ?: app(WorkspaceContext::class)->id();
    }

    protected function scopeSectionsToWorkspace(BelongsToMany $query, ?int $workspaceId): BelongsToMany
    {
        return $query
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $q) => $q->where('sections.workspace_id', $workspaceId))
            ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('sections.workspace_id'))
            ->orderBy('sections.name');
    }

    protected function applySectionFilter(Builder $query): void
    {
        $workspaceId = $this->currentWorkspaceId();

        if ($this->sectionFilter === 'all' || $this->sectionFilter === '') {
            return;
        }

        if ($this->sectionFilter === 'unassigned') {
            $query->whereDoesntHave('sections', fn (Builder $q) => $q
                ->withoutGlobalScope('workspace')
                ->when($workspaceId, fn (Builder $sq) => $sq->where('sections.workspace_id', $workspaceId))
                ->when(! $workspaceId, fn (Builder $sq) => $sq->whereNull('sections.workspace_id')));

            return;
        }

        if (is_numeric($this->sectionFilter)) {
            $sectionId = (int) $this->sectionFilter;
            $query->whereHas('sections', fn (Builder $q) => $q
                ->withoutGlobalScope('workspace')
                ->where('sections.id', $sectionId)
                ->when($workspaceId, fn (Builder $sq) => $sq->where('sections.workspace_id', $workspaceId)));
        }
    }

    protected function provisionAndAcceptInvite(User $student): CommunityInvite
    {
        $workspaceId = $this->resolveTargetWorkspaceId($student);

        $invite = CommunityInvite::query()->firstOrNew(['user_id' => $student->id]);
        $invite->workspace_id = $workspaceId;
        $invite->token ??= CommunityInvite::generateToken();
        $invite->invited_by ??= (int) Filament::auth()->id();
        $invite->accepted_at = now();
        $invite->save();

        return $invite;
    }

    protected function getStudentsBaseQuery(): Builder
    {
        $workspaceId = $this->currentWorkspaceId();

        $query = User::query()
            ->where('is_admin', false)
            ->with([
                'communityInvite',
                'sections' => fn (BelongsToMany $q) => $this->scopeSectionsToWorkspace($q, $workspaceId),
            ]);

        return $query->where(function (Builder $sub) use ($workspaceId) {
            $sub->whereHas('workspaces', fn (Builder $q) => $q
                ->when($workspaceId, fn (Builder $q) => $q->whereKey($workspaceId))
                ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('workspaces.id')))
                ->orWhereHas('sections', fn (Builder $q) => $q
                    ->withoutGlobalScope('workspace')
                    ->when($workspaceId, fn (Builder $q) => $q->where('sections.workspace_id', $workspaceId))
                    ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('sections.workspace_id')))
                ->orWhere(fn (Builder $q) => $q
                    ->when($workspaceId, fn (Builder $q) => $q->where('users.current_workspace_id', $workspaceId))
                    ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('users.current_workspace_id')));
        });
    }

    protected function resolveTargetWorkspaceId(User $student): int
    {
        $workspaceId = $this->currentWorkspaceId();

        if ($workspaceId) {
            return (int) $workspaceId;
        }

        if ($student->current_workspace_id) {
            return (int) $student->current_workspace_id;
        }

        $firstWorkspace = $student->workspaces()->first();
        if ($firstWorkspace) {
            return (int) $firstWorkspace->id;
        }

        $fallbackWorkspace = Workspace::query()->first();

        return (int) ($fallbackWorkspace?->id ?? 1);
    }
}
