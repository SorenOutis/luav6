<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AdminNotificationService
{
    /**
     * Send an event notification to Super Admins and Workspace Admins.
     */
    public static function notifyAdmins(
        string $title,
        string $body,
        Workspace|int|User|null|object $workspace = null,
        string $icon = 'heroicon-o-bell',
        string $color = 'info',
        ?string $url = null,
    ): void {
        $resolvedWorkspace = self::resolveWorkspace($workspace);
        $workspaceName = $resolvedWorkspace ? $resolvedWorkspace->name : 'Global';
        $workspaceId = $resolvedWorkspace?->id;

        // 1. Notify Super Admins with Workspace metadata clearly visible
        self::notifySuperAdmins($title, $body, $workspaceName, $icon, $color, $url);

        // 2. Notify Workspace Admins (only if associated with this workspace)
        if ($workspaceId) {
            self::notifyWorkspaceAdmins($title, $body, $workspaceId, $icon, $color, $url);
        }
    }

    /**
     * Resolve Workspace instance from argument, model object, User, or active context.
     */
    public static function resolveWorkspace(mixed $source): ?Workspace
    {
        if ($source instanceof Workspace) {
            return $source;
        }

        if (is_numeric($source) && $source > 0) {
            return Workspace::query()->find($source);
        }

        if (is_object($source) && isset($source->workspace_id) && $source->workspace_id) {
            $ws = Workspace::query()->find($source->workspace_id);
            if ($ws) {
                return $ws;
            }
        }

        if ($source instanceof User) {
            if ($source->currentWorkspace) {
                return $source->currentWorkspace;
            }

            $wsId = $source->workspaces()->value('workspaces.id');
            if ($wsId) {
                return Workspace::query()->find($wsId);
            }

            $sectionWsId = $source->sections()->whereNotNull('workspace_id')->value('workspace_id');
            if ($sectionWsId) {
                return Workspace::query()->find($sectionWsId);
            }
        }

        $contextId = app(WorkspaceContext::class)->id();
        if ($contextId) {
            $ws = Workspace::query()->find($contextId);
            if ($ws) {
                return $ws;
            }
        }

        $authUser = auth()->user();
        if ($authUser && $authUser->current_workspace_id) {
            $ws = Workspace::query()->find($authUser->current_workspace_id);
            if ($ws) {
                return $ws;
            }
        }

        return null;
    }

    /**
     * Send notification to Super Admins (with Workspace info in title and body).
     */
    protected static function notifySuperAdmins(
        string $title,
        string $body,
        string $workspaceName,
        string $icon,
        string $color,
        ?string $url,
    ): void {
        $superAdmins = User::query()
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->get();

        $formattedTitle = "[{$workspaceName}] {$title}";
        $formattedBody = "{$body}\nWorkspace: {$workspaceName}";

        foreach ($superAdmins as $superAdmin) {
            self::sendToUser($superAdmin, $formattedTitle, $formattedBody, $icon, $color, $url);
        }
    }

    /**
     * Send notification to Workspace Admins (belonging to this workspace ONLY).
     */
    protected static function notifyWorkspaceAdmins(
        string $title,
        string $body,
        int $workspaceId,
        string $icon,
        string $color,
        ?string $url,
    ): void {
        $workspaceAdmins = User::query()
            ->where('is_admin', true)
            ->where(fn ($q) => $q->where('is_super_admin', false)->orWhereNull('is_super_admin'))
            ->where(fn ($q) => $q
                ->where('current_workspace_id', $workspaceId)
                ->orWhereHas('workspaces', fn ($wq) => $wq->where('workspaces.id', $workspaceId))
                ->orWhereHas('sections', fn ($sq) => $sq->where('sections.workspace_id', $workspaceId)))
            ->get();

        foreach ($workspaceAdmins as $admin) {
            self::sendToUser($admin, $title, $body, $icon, $color, $url);
        }
    }

    /**
     * Send database notification to a single user and trigger bell update.
     */
    protected static function sendToUser(
        User $user,
        string $title,
        string $body,
        string $icon,
        string $color,
        ?string $url,
    ): void {
        try {
            $actions = [];
            if ($url) {
                $actions[] = [
                    'name' => 'view',
                    'label' => 'View',
                    'url' => $url,
                    'color' => null,
                    'isOutlined' => false,
                    'isDisabled' => false,
                    'size' => 'md',
                ];
            }

            $notificationData = [
                'id' => (string) Str::uuid(),
                'title' => $title,
                'body' => $body,
                'icon' => $icon,
                'iconColor' => $color,
                'actions' => $actions,
                'duration' => 'persistent',
            ];

            DatabaseNotification::query()->create([
                'id' => $notificationData['id'],
                'type' => FilamentNotification::class,
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'data' => $notificationData,
                'read_at' => null,
            ]);

            if (method_exists($user, 'notifyBell')) {
                $user->notifyBell();
            }
        } catch (Throwable $e) {
            Log::error('Failed to send notification to user '.$user->id.': '.$e->getMessage());
        }
    }
}
