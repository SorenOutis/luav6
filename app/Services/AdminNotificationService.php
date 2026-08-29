<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminNotificationService
{
    /**
     * Send an event notification to Super Admins and Workspace Admins.
     *
     * @param  string  $title Short title of the event
     * @param  string  $body Detailed description of the event
     * @param  Workspace|int|User|null  $workspace Workspace instance, ID, User, or null
     * @param  string  $icon Heroicon name
     * @param  string  $color Color (info, success, warning, danger, primary)
     * @param  string|null  $url Action URL (optional)
     */
    public static function notifyAdmins(
        string $title,
        string $body,
        Workspace|int|User|null $workspace = null,
        string $icon = 'heroicon-o-bell',
        string $color = 'info',
        ?string $url = null
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
     * Resolve Workspace instance from argument, User, or active context.
     */
    public static function resolveWorkspace(Workspace|int|User|null $source): ?Workspace
    {
        if ($source instanceof Workspace) {
            return $source;
        }

        if (is_numeric($source) && $source > 0) {
            return Workspace::query()->find($source);
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
        ?string $url
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
        ?string $url
    ): void {
        $workspaceAdmins = User::query()
            ->where('is_admin', true)
            ->where(function ($q): void {
                $q->where('is_super_admin', false)->orWhereNull('is_super_admin');
            })
            ->where(function ($q) use ($workspaceId): void {
                $q->where('current_workspace_id', $workspaceId)
                    ->orWhereHas('workspaces', function ($wq) use ($workspaceId): void {
                        $wq->where('workspaces.id', $workspaceId);
                    })
                    ->orWhereHas('sections', function ($sq) use ($workspaceId): void {
                        $sq->where('sections.workspace_id', $workspaceId);
                    });
            })
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
        ?string $url
    ): void {
        try {
            $notification = FilamentNotification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->color($color);

            if ($url) {
                $notification->actions([
                    Action::make('view')
                        ->button()
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);

            if (method_exists($user, 'notifyBell')) {
                $user->notifyBell();
            }
        } catch (Throwable $e) {
            Log::error("Failed to send notification to user {$user->id}: ".$e->getMessage());
        }
    }
}
