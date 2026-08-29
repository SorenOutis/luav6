<?php

namespace App\Listeners;

use App\Services\AdminNotificationService;
use Illuminate\Auth\Events\Login;

class NotifyAdminsOnUserLogin
{
    /**
     * Handle the Login event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $workspace = AdminNotificationService::resolveWorkspace($user);

        AdminNotificationService::notifyAdmins(
            title: 'User Logged In',
            body: "User {$user->name} ({$user->email}) logged in.",
            workspace: $workspace,
            icon: 'heroicon-o-arrow-right-on-rectangle',
            color: 'info',
        );
    }
}
