<?php

namespace App\Observers;

use App\Models\AnonymousMessage;
use App\Services\AdminNotificationService;
use Throwable;

class AnonymousMessageObserver
{
    /**
     * Handle the AnonymousMessage "created" event.
     */
    public function created(AnonymousMessage $message): void
    {
        try {
            $workspace = AdminNotificationService::resolveWorkspace($message);

            AdminNotificationService::notifyAdmins(
                title: 'New Anonymous Message',
                body: 'A new anonymous message was posted.',
                workspace: $workspace,
                icon: 'heroicon-o-chat-bubble-left-ellipsis',
                color: 'warning',
            );
        } catch (Throwable) {
        }
    }
}
