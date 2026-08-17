<?php

namespace App\Support;

use App\Models\Workspace;
use Closure;

/** Request/job-scoped tenant context safe for Octane and queue workers. */
class WorkspaceContext
{
    private bool $resolved = false;

    private bool $explicit = false;

    private bool $inspecting = false;

    private int|string|null $resolvedUserId = null;

    private ?int $workspaceId = null;

    public function id(): ?int
    {
        if ($this->explicit) {
            return $this->workspaceId;
        }

        $currentUserId = auth()->id();
        if (! $this->resolved || $this->resolvedUserId !== $currentUserId) {
            $this->resolveFromAuthenticatedUser();
        }

        return $this->workspaceId;
    }

    public function workspace(): ?Workspace
    {
        $id = $this->id();

        return $id ? Workspace::query()->find($id) : null;
    }

    public function isInspecting(): bool
    {
        $this->id();

        return $this->inspecting;
    }

    public function inspect(Workspace $workspace): void
    {
        abort_if($workspace->isArchived(), 422, 'Archived workspaces cannot be entered.');
        session()->put('workspace_inspection_id', $workspace->id);
        $this->workspaceId = $workspace->id;
        $this->resolved = true;
        $this->explicit = false;
        $this->inspecting = true;
        $this->resolvedUserId = auth()->id();
    }

    public function stopInspecting(): void
    {
        session()->forget('workspace_inspection_id');
        $this->clear();
    }

    public function set(Workspace|int|null $workspace): void
    {
        $this->workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $this->resolved = true;
        $this->explicit = true;
        $this->inspecting = false;
        $this->resolvedUserId = auth()->id();
    }

    public function clear(): void
    {
        $this->workspaceId = null;
        $this->resolved = false;
        $this->explicit = false;
        $this->inspecting = false;
        $this->resolvedUserId = null;
    }

    public function run(Workspace|int|null $workspace, Closure $callback): mixed
    {
        $previous = [
            $this->resolved,
            $this->explicit,
            $this->inspecting,
            $this->resolvedUserId,
            $this->workspaceId,
        ];
        $this->set($workspace);

        try {
            return $callback();
        } finally {
            [
                $this->resolved,
                $this->explicit,
                $this->inspecting,
                $this->resolvedUserId,
                $this->workspaceId,
            ] = $previous;
        }
    }

    private function resolveFromAuthenticatedUser(): void
    {
        $this->resolved = true;
        $this->explicit = false;
        $this->inspecting = false;
        $this->workspaceId = null;
        $user = auth()->user();
        $this->resolvedUserId = $user?->getAuthIdentifier();

        if (! $user) {
            return;
        }

        if ($user->isSuperAdmin()) {
            $inspectionId = session()->get('workspace_inspection_id');
            if ($inspectionId && Workspace::query()->whereKey($inspectionId)->whereNull('archived_at')->exists()) {
                $this->workspaceId = (int) $inspectionId;
                $this->inspecting = true;

                return;
            }
        }

        // Super admins retain platform-wide reads in model scopes, but their
        // writes and workspace-aware settings still target this active tenant.
        $candidate = $user->current_workspace_id;
        if ($candidate && Workspace::query()->whereKey($candidate)->whereNull('archived_at')->exists()) {
            $this->workspaceId = (int) $candidate;

            return;
        }

        $this->workspaceId = $user->workspaces()
            ->whereNull('workspaces.archived_at')
            ->orderByRaw("CASE workspace_user.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('workspaces.id')
            ->value('workspaces.id');
    }
}
