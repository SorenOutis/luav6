<?php

namespace App\Ai\Tools;

use App\Exceptions\PendingAiActionException;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PendingAiActionService;
use App\Support\WorkspaceContext;

abstract class PendingWriteTool
{
    public function __construct(
        protected ?PendingAiActionService $pendingActions = null,
        protected ?int $chatSessionId = null,
    ) {
        $this->pendingActions ??= app(PendingAiActionService::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{field: string, before: mixed, after: mixed}>  $changes
     */
    protected function stageAction(
        string $type,
        string $title,
        string $summary,
        array $payload,
        array $changes,
        ?int $targetWorkspaceId = null,
    ): string {
        $targetWorkspaceId ??= $this->workspaceId();
        $targetWorkspaceName = $targetWorkspaceId
            ? (Workspace::query()->find($targetWorkspaceId)?->name ?? $this->workspaceName())
            : $this->workspaceName();

        $hasWorkspace = false;
        foreach ($changes as $change) {
            if (isset($change['field']) && in_array(strtolower((string) $change['field']), ['workspace', 'target workspace'], true)) {
                $hasWorkspace = true;
                break;
            }
        }

        if (! $hasWorkspace) {
            $changes[] = [
                'field' => 'Workspace',
                'before' => null,
                'after' => $targetWorkspaceName,
            ];
        }

        try {
            $action = $this->pendingActions->stage(
                $type,
                $title,
                $summary,
                $payload,
                ['changes' => $changes],
                $this->chatSessionId,
                $targetWorkspaceId,
            );
        } catch (PendingAiActionException $exception) {
            return 'Error preparing approval: '.$exception->getMessage();
        }

        $expiresAt = $action->expires_at?->format('g:i A');

        return 'PENDING HUMAN APPROVAL — no write was executed. '
            ."A review card for \"{$action->title}\" is now visible to the administrator"
            .($expiresAt ? " and expires at {$expiresAt}" : '')
            .'. The administrator must review the exact diff and click Approve; do not ask them to type a confirmation and do not call this tool again for the same change.';
    }

    protected function workspaceName(): string
    {
        return app(WorkspaceContext::class)->workspace()?->name ?? 'Active Workspace';
    }

    protected function workspaceId(): ?int
    {
        return app(WorkspaceContext::class)->id();
    }

    protected function adminError(): ?string
    {
        return auth()->user()?->is_admin ? null : 'Only admins can use this tool.';
    }

    /**
     * Resolve a user by ID, name, or email within the active workspace.
     * For super administrators, falls back to platform-wide matching if not in the active workspace.
     */
    protected function findWorkspaceUser(int|string|null $identifier = null, ?string $name = null, ?string $email = null): ?User
    {
        $workspaceId = $this->workspaceId();
        $admin = auth()->user();

        $numericId = null;
        $searchEmail = $email ? strtolower(trim($email)) : null;
        $searchName = $name ? trim($name) : null;

        if (is_numeric($identifier)) {
            $numericId = (int) $identifier;
        } elseif (is_string($identifier) && trim($identifier) !== '') {
            $trimmed = trim($identifier);
            $cleanId = ltrim($trimmed, '#');
            if (is_numeric($cleanId)) {
                $numericId = (int) $cleanId;
            } elseif (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                $searchEmail ??= strtolower($trimmed);
            } else {
                $searchName ??= $trimmed;
            }
        }

        if (! $numericId && ! $searchEmail && ! $searchName) {
            return null;
        }

        $applyIdentifierFilter = function ($query) use ($numericId, $searchEmail, $searchName) {
            $query->where(function ($q) use ($numericId, $searchEmail, $searchName) {
                if ($numericId) {
                    $q->orWhere('users.id', $numericId);
                }
                if ($searchEmail) {
                    $q->orWhere('users.email', $searchEmail);
                }
                if ($searchName) {
                    $q->orWhere('users.name', $searchName)
                        ->orWhere('users.name', 'like', "%{$searchName}%");
                }
            });
        };

        if ($workspaceId) {
            $workspaceUser = User::query()
                ->where(function ($q) use ($workspaceId) {
                    $q->whereHas('workspaces', fn ($sub) => $sub->whereKey($workspaceId))
                        ->orWhereHas('sections', fn ($sub) => $sub->where('sections.workspace_id', $workspaceId))
                        ->orWhere('users.current_workspace_id', $workspaceId);
                })
                ->where($applyIdentifierFilter)
                ->first();

            if ($workspaceUser) {
                return $workspaceUser;
            }
        }

        if ($admin?->isSuperAdmin()) {
            return User::query()
                ->where($applyIdentifierFilter)
                ->first();
        }

        return null;
    }

    /**
     * Resolve a user by ID, name, or email across the platform.
     */
    protected function resolveUserByIdentifiers(int|string|null $identifier = null, ?string $name = null, ?string $email = null): ?User
    {
        $numericId = null;
        $searchEmail = $email ? strtolower(trim($email)) : null;
        $searchName = $name ? trim($name) : null;

        if (is_numeric($identifier)) {
            $numericId = (int) $identifier;
        } elseif (is_string($identifier) && trim($identifier) !== '') {
            $trimmed = trim($identifier);
            $cleanId = ltrim($trimmed, '#');
            if (is_numeric($cleanId)) {
                $numericId = (int) $cleanId;
            } elseif (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                $searchEmail ??= strtolower($trimmed);
            } else {
                $searchName ??= $trimmed;
            }
        }

        if (! $numericId && ! $searchEmail && ! $searchName) {
            return null;
        }

        return User::query()
            ->where(function ($q) use ($numericId, $searchEmail, $searchName) {
                if ($numericId) {
                    $q->orWhere('users.id', $numericId);
                }
                if ($searchEmail) {
                    $q->orWhere('users.email', $searchEmail);
                }
                if ($searchName) {
                    $q->orWhere('users.name', $searchName)
                        ->orWhere('users.name', 'like', "%{$searchName}%");
                }
            })
            ->first();
    }

    /**
     * Resolve the target workspace ID for a user.
     */
    protected function resolveUserWorkspaceId(User $user): int
    {
        $workspaceId = $this->workspaceId();

        if ($workspaceId && (
            (int) $user->current_workspace_id === (int) $workspaceId
            || $user->workspaces()->whereKey($workspaceId)->exists()
            || $user->sections()->where('sections.workspace_id', $workspaceId)->exists()
        )) {
            return $workspaceId;
        }

        return (int) (
            $user->workspaces()->value('workspaces.id')
            ?? $user->current_workspace_id
            ?? $user->sections()->value('sections.workspace_id')
            ?? $workspaceId
            ?? 1
        );
    }
}
