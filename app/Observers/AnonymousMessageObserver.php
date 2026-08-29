<?php

namespace App\Observers;

use App\Models\AnonymousMessage;
use App\Services\AdminNotificationService;

class AnonymousMessageObserver
{
    /**
     * Handle the AnonymousMessage "created" event.
     */
    public function created(AnonymousMessage $message): void
    {
        $workspace = $message->section?->workspace ?? $message->user?->currentWorkspace;

        AdminNotificationService::notifyAdmins(
            title: 'New Anonymous Message',
            body: 'A new anonymous message was posted.',
            workspace: $workspace,
            icon: 'heroicon-o-chat-bubble-left-ellipsis',
            color: 'warning',
        );
    }
}
