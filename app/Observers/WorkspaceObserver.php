<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Services\AdminNotificationService;
use Throwable;

class WorkspaceObserver
{
    /**
     * Handle the Workspace "created" event.
     */
    public function created(Workspace $workspace): void
    {
        try {
            AdminNotificationService::notifyAdmins(
                title: 'New Workspace Created',
                body: "Workspace '{$workspace->name}' was created.",
                workspace: $workspace,
                icon: 'heroicon-o-building-office-2',
                color: 'primary',
            );
        } catch (Throwable) {
        }
    }
}
