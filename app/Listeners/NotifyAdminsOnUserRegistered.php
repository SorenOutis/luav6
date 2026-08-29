<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Auth\Events\Registered;
use Throwable;

class NotifyAdminsOnUserRegistered
{
    /**
     * Handle the Registered event.
     */
    public function handle(Registered $event): void
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
                title: 'New User Registered',
                body: "User {$name} ({$email}) registered.",
                workspace: $workspace,
                icon: 'heroicon-o-user-plus',
                color: 'success',
                url: '/admin/users',
            );
        } catch (Throwable) {
        }
    }
}
