<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Auth\Events\Login;
use Throwable;

class NotifyAdminsOnUserLogin
{
    /**
     * Handle the Login event.
     */
    public function handle(Login $event): void
    {
        try {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            $name = $user->name ?? 'User';
            $email = $user->email ?? 'No email';
            $workspace = AdminNotificationService::resolveWorkspace($user);

            AdminNotificationService::notifyAdmins(
                title: 'User Logged In',
                body: "User {$name} ({$email}) logged in.",
                workspace: $workspace,
                icon: 'heroicon-o-arrow-right-on-rectangle',
                color: 'info',
            );
        } catch (Throwable) {
        }
    }
}
