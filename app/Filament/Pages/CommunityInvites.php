<?php

namespace App\Filament\Pages;

use App\Models\CommunityInvite;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
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

    public ?int $workspaceId = null;

    public function mount(): void
    {
        $context = app(WorkspaceContext::class);
        $this->workspaceId = $context->id();
    }

    public function getSelectedStudentProperty(): ?User
    {
        if (! $this->selectedUserId) {
            return null;
        }

        return User::query()
            ->with(['communityInvite', 'sections'])
            ->find($this->selectedUserId);
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

        $workspaceId = $this->resolveTargetWorkspaceId($student);

        $invite = CommunityInvite::query()->firstOrNew(['user_id' => $student->id]);
        $invite->workspace_id = $workspaceId;
        $invite->token ??= CommunityInvite::generateToken();
        $invite->invited_by ??= (int) Filament::auth()->id();
        $invite->accepted_at = now();
        $invite->save();

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

        return $this->getStudentsBaseQuery()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();
    }

    /**
     * Get students roster list for the workspace table.
     *
     * @return Collection<int, User>
     */
    public function getStudentsRosterProperty(): Collection
    {
        $query = $this->getStudentsBaseQuery();

        if ($this->searchQuery !== '') {
            $search = trim($this->searchQuery);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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

        return $query
            ->orderBy('name')
            ->limit(50)
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

    protected function getStudentsBaseQuery(): Builder
    {
        $context = app(WorkspaceContext::class);
        $user = Filament::auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;
        $workspaceId = $context->id();

        $query = User::query()
            ->where('is_admin', false)
            ->with(['communityInvite', 'sections']);

        if ($isSuperAdmin && ! $context->isInspecting()) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($workspaceId) {
            $sub->whereHas('workspaces', fn (Builder $q) => $q
                ->when($workspaceId, fn (Builder $q) => $q->whereKey($workspaceId))
                ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('workspaces.id')))
                ->orWhereHas('sections', fn (Builder $q) => $q
                    ->when($workspaceId, fn (Builder $q) => $q->where('sections.workspace_id', $workspaceId))
                    ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('sections.workspace_id')))
                ->orWhere(fn (Builder $q) => $q
                    ->when($workspaceId, fn (Builder $q) => $q->where('users.current_workspace_id', $workspaceId))
                    ->when(! $workspaceId, fn (Builder $q) => $q->whereNull('users.current_workspace_id')));
        });
    }

    protected function resolveTargetWorkspaceId(User $student): int
    {
        $context = app(WorkspaceContext::class);
        $workspaceId = $context->id();

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
